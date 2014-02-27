# Altax chef plugin

Runs chef-solo via [altax](https://github.com/kohkimakimoto/altax) and Git.

> NOTE: This product is in development stag, So I sometimes break code.

## Requirement

Depends on my personal environment.

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
        "repo" => "git repository path to store chef repository"
    ))
    ;

## Usage

See 

    altax chef -h


