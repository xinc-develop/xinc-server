<?php
/*
 * Xinc - Continuous Integration.
 * First Xinc Engine running on XML
 *
 * PHP version 5
 *
 * @category  Development
 * @package   Xinc.Engine
 * @author    Arno Schneider <username@example.org>
 * @copyright 2007 Arno Schneider, Barcelona
 * @license   http://www.gnu.org/copyleft/lgpl.html GNU/LGPL, see license.php
 *            This file is part of Xinc.
 *            Xinc is free software; you can redistribute it and/or modify
 *            it under the terms of the GNU Lesser General Public License as
 *            published by the Free Software Foundation; either version 2.1 of
 *            the License, or (at your option) any later version.
 *
 *            Xinc is distributed in the hope that it will be useful,
 *            but WITHOUT ANY WARRANTY; without even the implied warranty of
 *            MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *            GNU Lesser General Public License for more details.
 *
 *            You should have received a copy of the GNU Lesser General Public
 *            License along with Xinc, write to the Free Software Foundation,
 *            Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @homepage  http://xinc-develop.github.io/
 */
namespace Xinc\Server\Engine;

use Xinc\Core\Engine\EngineInterface;
use Xinc\Core\Engine\Base;
use Xinc\Core\Build\Build;
use Xinc\Core\Build\BuildInterface;
use Xinc\Core\Project\Project;
use Xinc\Core\Task\Slot;

/**
 * The Sunrise engine
 */
class Sunrise extends Base implements EngineInterface
{
    const NAME = 'Sunrise';

    /**
     * get the name of this engine
     *
     * @return string
     */
    public function getName()
    {
        return self::NAME;
    }

    protected function endBuild(BuildInterface $build)
    {
        $project = $build->getProject()->getName();
        $status = $build->getStatusString();
        $this->log->info("END BUILDING PROJECT {$project} WITH STATUS $status.");
    }

    /**
     * Serializes a build an catches the exceptions
     *
     * @param Xinc_Build_Interface $build
     */
    protected function _serializeBuild(BuildInterface $build)
    {
        try {
           $build->serialize();
        } catch (Xinc_Build_Exception_NotRun $e1) {
            $build->error('Build cannot be serialized, it did not run.');
        } catch (Xinc_Build_Exception_Serialization $e2) {
            $build->error('Build could not be serialized properly.');
        } catch (Xinc_Build_History_Exception_Storage $e3) {
            $build->error('Build history could not be stored.');
        } catch (Exception $e4) {
            $build->error('Unknown error occured while serializing the build.');
        }
    }


    /**
     * Setup a project for the engine and setup a build object from
     * project configuration.
     *
     * @param Xinc::Core::Project::Project $project A project inside this engine.
     *
     * @return BuildInterface
     */
    public function setupBuild(Project $project)
    {
        $build = new Build($this, $project);
        $build->setLogger($this->log);
        $build->setNumber(1);
        $this->setupBuildProperties($build);
        $this->setupConfigProperties($build);
        $this->parseProjectConfig($build,$project->getConfigXml());
        return $build;
    }

    /**
     * Process a build
     *
     * @param Xinc::Core::Build::BuildInterface $build
     */
    public function build(BuildInterface $build)
    {
        $this->build = $build;
        $buildTime = time();
        $startTime = time() + microtime(true);
        $build->setBuildTime($buildTime);
        $build->init();
        if ( BuildInterface::STOPPED === $build->getStatus() ) {
            return $this->endBuild($build);
        }
        /**
         * Increasing the build number, if it fails we need to decrease again
         */
        if ($build->getLastBuild()->getStatus() === BuildInterface::PASSED
            ||
            ($build->getLastBuild()->getStatus() === null &&
             $build->getLastBuild()->getStatus() !== BuildInterface::STOPPED)) {
            $build->setNumber($build->getNumber()+1);
            //$this->updateBuildTasks($build);
        }
        $build->updateTasks();
        $build->process(Slot::INIT_PROCESS);

        if ( BuildInterface::STOPPED === $build->getStatus() ) {
            $this->log->info('Build of Project stopped in INIT phase');
            $build->setLastBuild();
            return $this->endBuild($build);
        }

        $this->log->info("CHECKING PROJECT {$build->getProject()->getName()}");
        $build->process(Slot::PRE_PROCESS);
        if ( BuildInterface::STOPPED === $build->getStatus() ) {
            $this->log->info("Build of Project stopped, no build necessary");
            $build->setStatus(BuildInterface::INITIALIZED);
            $build->setLastBuild();
            return $this->endBuild($build);
        } else if ( BuildInterface::FAILED === $build->getStatus() ) {
            //$build->setBuildTime($buildTime);
            $build->updateTasks();
            $this->log->error("Build failed");
            /**
             * Process failed in the pre-process phase, we need
             * to run post-process to maybe inform about the failed build
             */
            $build->process(Slot::POST_PROCESS);
            /**
             * Issue 79, we need to serialize the build after failure in preprocess
             */
            /**
             * set the "time it took to build" on the build
             */
            $endTime = time() + microtime(true);
            $build->getStatistics()->set('build.duration', $endTime - $startTime);

            $this->_serializeBuild($build);
            return $this->endBuild($build);

        } else if ( BuildInterface::PASSED === $build->getStatus() ) {

            $this->log->info("Code not up to date, building project");
            //$build->setBuildTime($buildTime);



            $build->updateTasks();


            $build->process(Slot::PROCESS);
            if ( BuildInterface::PASSED == $build->getStatus() ) {

                $build->updateTasks();
                $this->log->info("BUILD PASSED");
            } else if ( Xinc_Build_Interface::STOPPED == $build->getStatus() ) {
                //$build->setNumber($build->getNumber()-1);
                $build->updateTasks();
                $build->warn("BUILD STOPPED");
            } else if (BuildInterface::FAILED == $build->getStatus() ) {
                //if ($build->getLastBuild()->getStatus() == Xinc_Build_Interface::PASSED) {
                //    $build->setNumber($build->getNumber()+1);
                //}

                $build->updateTasks();
                $build->error("BUILD FAILED");
            }

            $processingPast = $build->getStatus();
            /**
             * Post-Process is run on Successful and Failed Builds
             */
            $build->process(Slot::POST_PROCESS);

            /**
             * set the "time it took to build" on the build
             */
            $endTime = time() + microtime(true);
            $build->getStatistics()->set('build.duration', $endTime - $startTime);


            $this->_serializeBuild($build);

            return $this->endBuild($build);

        } else if ( BuildInterface::INITIALIZED === $build->getStatus() ) {
            //$build->setBuildTime($buildTime);
            if ($build->getLastBuild()->getStatus() === null) {
                $build->setNumber($build->getNumber()-1);
            }
            $build->setStatus(BuildInterface::STOPPED);
            $this->_serializeBuild($build);
            return $this->endBuild($build);
        } else {
            $build->setStatus(BuildInterface::STOPPED);
            $build->setLastBuild();
            return $this->endBuild($build);
        }
    }
}
