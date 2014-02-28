# Altax chef plugin

Runs chef-solo via [altax](https://github.com/kohkimakimoto/altax) and Git.

> NOTE: This product is in development stag, So I sometimes break code.

## Requirement

I tested it on the following environments.

* CentOS6
* PHP5.4

## Installation

Edit your `.altax/composer.json` file like the following.

    {
      "require": {
        "kohkimakimoto/altax-chef": "dev-master"
      }
    }

Run altax update.

    $ altax update

Add the following code your `.altax/config.php` file.

    Task::register("chef", 'Altax\Contrib\Chef\Command\ChefCommand')
    ->config(array(
        "repo" => "git@github.com:your/chef-reposigory.git"
    ))
    ;

## Usage

Installs chef package to remote node.

    altax chef node [node...] --prepare

Runs chef-solo using run_list `nodes/${HOSTNAME}.json`

    altax chef node [node...]

