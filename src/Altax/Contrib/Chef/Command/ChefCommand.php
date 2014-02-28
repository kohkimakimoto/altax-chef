<?php
namespace Altax\Contrib\Chef\Command;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Finder\Finder;

class ChefCommand extends \Altax\Command\Command
{
    const CHEF_INSTALL_COMMAND = "curl -L https://www.opscode.com/chef/install.sh | bash";

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
        $branch = isset($config["branch"]) ? $config["branch"] : "master"; 
        $dir = isset($config["dir"]) ? $config["dir"] : "/var/chef"; 
        $berks = isset($config["berks"]) ? $config["berks"] : "/opt/chef/embedded/bin/berks"; 
        $key = isset($config["key"]) ? $config["key"] : getenv("HOME")."/.ssh/id_rsa"; 
        $chefInstallCommand = isset($config["chef_install_command"]) ? $config["chef_install_command"] : self::CHEF_INSTALL_COMMAND; 

        $target = $input->getArgument("target");
        $runBerks = $input->getOption("berks");
        $noSolo = $input->getOption("no-solo");
        $onlyPrepare = $input->getOption("prepare");

        $task->exec(function($process) use (
            $onlyPrepare,
            $key, 
            $chefInstallCommand,
            $dir,
            $branch,
            $repo, 
            $berks, 
            $runBerks, 
            $noSolo,
            $task) {

            $node = $process->getNode();

            //
            // chef chef installation
            //
            $prepare = $onlyPrepare;

            if ($process->run("test -e /opt/chef/bin/chef-solo")->isFailed()) {
                $task->writeln("Run preparing. Not found chef-solo.");
                $prepare = true;
            }

            //
            // prepare process
            //
            if ($prepare) {
                // Install git
                $process->run("yum install -y git", array("user" => "root"));
                // Install chef
                $process->run($chefInstallCommand, array("user" => "root"));
                // Install berkself gem
                if ($process->run("test -e /opt/chef/embedded/bin/berks")->isFailed()) {
                    $process->run("/opt/chef/embedded/bin/gem install berkshelf --no-rdoc --no-ri", array("user" => "root"));
                }
                // Copy ssh private key
                $tmp = "/tmp/".uniqid().".key";
                $process->put($key, $tmp);
                $process->run(array(
                    "cp ${tmp} /root/.ssh/id_rsa",
                    "chmod 600 /root/.ssh/id_rsa",
                    "rm ${tmp}"
                    ), array("user" => "root"));

                if ($onlyPrepare) {
                    return;
                }
            }

            //
            // provisioning process
            //
            // Get chef repository
            $ret = null;
            if ($process->run("test -d $dir")->isFailed()) {
                $ret = $process->run(array(
                    "git clone $repo $dir", 
                    "cd $dir", 
                    "cd checkout $branch"
                    ), 
                    array("user" => "root")
                );
            } else {
                $ret = $process->run(array(
                    "git pull"
                    ), 
                    array("user" => "root", "cwd" => $dir)
                );
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
                $nodeName = $process->get;


                $process->run(array(
                    "unset GEM_HOME && unset GEM_PATH",
                    "cd $dir",
                    "chef-solo -c $dir/config/solo.rb -j $dir/nodes/\$HOSTNAME.json"
                    ), array("user" => "root"));
            }

        }, $target);


    }
}
