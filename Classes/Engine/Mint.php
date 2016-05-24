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
 * The Mint engine
 *
 * This is a very simple engine, which does not store anything about a
 * build.
 */
class Mint extends Base
{
    const NAME = 'Mint';

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
        $build->setNumber(1);
        $this->setupBuildProperties($build);
        $this->setupConfigProperties($build);
        $this->parseProjectConfig($build,$project->getConfigXml());
        $this->log->verbose("Setup tasks.");
        $build->setupTasks();
        return $build;
    }

    /**
     * Process a build
     *
     * @param Xinc::Core::Build::BuildInterface $build
     */
    public function build(BuildInterface $build)
    {
        $buildTime = time();
        $startTime = time() + microtime(true);
        $build->setBuildTime($buildTime);

        if ( !$this->initBuild($build) ) {
            return $this->endBuild($build);
        }

        $build->process(Slot::INIT_PROCESS);
        $this->debugBuildProperties($build);
        if ( $build->isFinished() ) {
            $this->log->info('Build of Project stopped in INIT phase');
            $build->setLastBuild();
            return $this->endBuild($build);
        }

        $build->info("CHECKING PROJECT");
        $build->process(Slot::PRE_PROCESS);
        $build->debug("RESULT STATUS IS " . $build->getStatusString());
        if ( $build->isFinished() ) {
            $this->log->info("Build of Project stopped, no build necessary");
            $build->setStatus(BuildInterface::INITIALIZED);
            $build->setLastBuild();
            return $this->endBuild($build);
        }
        else if ( BuildInterface::FAILED === $build->getStatus() ) {
            $this->log->error("Build failed");
            /**
             * Process failed in the pre-process phase, we need
             * to run post-process to maybe inform about the failed build
             */
            $build->process(Slot::POST_PROCESS);
            return $this->endBuild($build);
        }
        else if ( BuildInterface::PASSED === $build->getStatus() ) {
            $this->log->info("Code not up to date, building project");
            $build->process(Slot::PROCESS);
            if ( BuildInterface::PASSED == $build->getStatus() ) {
                $build->info("BUILD PASSED");
            }
            else if ( BuildInterface::STOPPED == $build->getStatus() ) {
                $build->warn("BUILD STOPPED");
            }
            else if (BuildInterface::FAILED == $build->getStatus() ) {
                $build->error("BUILD FAILED");
            }

            $build->process(Slot::POST_PROCESS);
            return $this->endBuild($build);

        }
        else if ( BuildInterface::INITIALIZED === $build->getStatus() ) {
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

    protected function debugBuildProperties($build)
    {
        $props = $build->getAllProperties();
        ksort($props);
        foreach($props as $key => $value) {
            $build->debug("Property {$key}: {$value}");
        }
    }
}
