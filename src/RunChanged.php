<?php

declare(strict_types=1);

namespace Codeception;

use Codeception\Configuration as Config;
use Codeception\Event\PrintResultEvent;
use Codeception\Test\Descriptor;
use function array_key_exists;
use function file_put_contents;
use function implode;
use function is_file;
use function str_replace;
use function unlink;

/**
 * An extension for taking tests that have changes against the master branch.
 *
 * Saves list tests into tests/_output/changed in order to run changed tests.
 *
 * To run changed tests just run the `changed` group:
 *
 * php codecept run -g changed
 *
 * You need to enable the extension in the codeception.yml file and add the group name and path for the file "changed"
 *
 * ``` yaml
 * extensions:
 *     enabled: [Codeception\Extension\RunChanged]
 * groups:
 *     changed: tests/_output/changed
 * ```
 */
class RunChanged extends Module
{
    protected string $group = 'changed';
    private const BRANCH_NAME_PARAM = 'branch_name';

    public static array $events = [
        Events::RESULT_PRINT_AFTER => 'saveFailed'
    ];

    public function _initialize(): void
    {
        if (array_key_exists('changed-group', $this->config) && $this->config['changed-group']) {
            $this->group = $this->config['changed-group'];
        }
        $logPath = str_replace(Config::projectDir(), '', Config::projectDir()); // get local path to logs
        $this->_reconfigure(['groups' => [$this->group => $logPath . $this->group]]);
        $this->getChangedTests();
    }

    private function getChangedTests()
    {
        $branchName = $this->getBranchName();
        $modifiedFiles = shell_exec("git diff --name-only " . $branchName . "...HEAD");
        if ($modifiedFiles) {
            $modifiedFiles = array_filter(explode("\n", $modifiedFiles), function ($file) {
                return str_ends_with($file, "Cest.php");
            });
            $output = [];
            $groupFile = Config::projectDir() . $this->group;
            if (is_file($groupFile)) {
                unlink($groupFile);
            }
            foreach ($modifiedFiles as $file) {
                $output[] = $file;
            }
            file_put_contents($groupFile, implode("\n", $output));
        }
    }

    private function getBranchName()
    {
        return $this->config[self::BRANCH_NAME_PARAM] ?? 'origin/master';
    }

//    public function saveFailed(PrintResultEvent $event): void
//    {
//        $file = Config::projectDir() . $this->group;
//        $result = $event->getResult();
//        if ($result->wasSuccessful()) {
//            if (is_file($file)) {
//                unlink($file);
//            }
//            return;
//        }
//        $output = [];
//        foreach ($result->failures() as $fail) {
//            $output[] = $this->localizePath(Descriptor::getTestFullName($fail->getTest()));
//        }
//        foreach ($result->errors() as $fail) {
//            $output[] = $this->localizePath(Descriptor::getTestFullName($fail->getTest()));
//        }
//
//        file_put_contents($file, implode("\n", $output));
//    }
//
//    protected function localizePath(string $path): string
//    {
//        $root = realpath(Config::projectDir()) . DIRECTORY_SEPARATOR;
//        if (substr($path, 0, strlen($root)) === $root) {
//            return substr($path, strlen($root));
//        }
//        return $path;
//    }
//
//    public function retryFailedTests($attempts)
//    {
//        for ($i = 0; $i < $attempts; $i++) {
//            $this->debug("Attempt " . ($i + 1) . ":\n");
//            $this->getModule('WebDriver')->_failed();
//            $this->getModule('WebDriver')->_after($this->getScenario());
//        }
//    }
}
