<?php
namespace Altax\Contrib\Chef\Command;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Finder\Finder;

class ChefCommand extends \Altax\Command\Command
{
    public $chefRpm = "https://opscode-omnibus-packages.s3.amazonaws.com/el/6/x86_64/chef-11.10.4-1.el6.x86_64.rpm";

    protected function configure()
    {
        $this
            ->setDescription("Runs chef-solo via altax.")
            ->addArgument(
                'target',
                InputArgument::IS_ARRAY | InputArgument::REQUIRED,
                'Target nodes or roles to run chef-solo.'
            )
            ->addOption(
                'berks',
                null,
                InputOption::VALUE_NONE,
                'Runs berkself even if exists cookbooks.'
            )
            ->addOption(
                'no-solo',
                null,
                InputOption::VALUE_NONE,
                'Do not run chef-solo.'
            )
            ->addOption(
                'prepare',
                null,
                InputOption::VALUE_NONE,
                'Prepare chef-solo (installing chef and something).'
            )
            ;
    }

    protected function fire($task)
    {
        $input = $task->getInput();
        $output = $task->getOutput();

        $config = $this->getTaskConfig();
        if (!isset($config["repo"])) {
            throw new \RuntimeException("You must set config key 'repo'.");
        }

        $repo = $config["repo"];
        $dir = isset($config["dir"]) ? $config["dir"] : "/var/chef"; 
        $berks = isset($config["berks"]) ? $config["berks"] : "/opt/chef/embedded/bin/berks"; 

        $target = $input->getArgument("target");
        $runBerks = $input->getOption("berks");
        $noSolo = $input->getOption("no-solo");
        $prepare = $input->getOption("prepare");

        if ($prepare) {
            // Prepare
            $chefRpm = $this->chefRpm;

            $task->exec(function($process) use ($chefRpm, $dir, $repo, $berks, $runBerks, $noSolo) {

                // Install git
                $process->run("yum install -y git", array("user" => "root"));
                // Install chef
                $process->run("rpm -ivh $chefRpm", array("user" => "root"));
                // Install berkself gem
                $process->run("/opt/chef/embedded/bin/gem install berkshelf  --no-rdoc --no-ri", array("user" => "root"));

            }, $target);

        } else {
            // Run chef-solo

            $task->exec(function($process) use ($dir, $repo, $berks, $runBerks, $noSolo) {

                $node = $process->getNode();

                // Get chef repository
                $ret = null;
                if ($process->run("test -d $dir")->isFailed()) {
                    $ret = $process->run("git clone $repo $dir");
                } else {
                    $ret = $process->run("git pull", array("cwd" => $dir));
                }

                if ($ret->isFailed()) {
                    throw new \RuntimeException("Got a error.");
                }

                // Check existing the cookbooks directory
                if ($process->run("test -d $dir/cookbooks")->isFailed()) {
                    $runBerks = true;
                }

                if ($runBerks) {
                    // Run berkself
                    // "unset" Prevent to load system wide ruby and gems.
                    $process->run(array(
                        "unset GEM_HOME && unset GEM_PATH",
                        "cd $dir",
                        "$berks --path cookbooks"
                        ), array("user" => "root"));

                }

                if (!$noSolo) {
                    // Run chef-solo
                    $nodeName = $node->getName();
                    $process->run(array(
                        "unset GEM_HOME && unset GEM_PATH",
                        "cd $dir",
                        "chef-solo -c $dir/config/solo.rb -j $dir/nodes/${nodeName}.json"
                        ), array("user" => "root"));
                }

            }, $target);

        }


    }
}
