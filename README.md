# undocker

A ~~hack~~trick to make life with `docker` on a Mac less miserable.

## Installation

```sh
git clone https://github.com/rimi-itk/undocker
```

## Usage

### Configuration

Create a file named `.env.undocker.local` in your repository and set environment
variables that you want to override in this file, e.g.

```sh
# .env.undocker.local

# Use database from running docker-compose setup
DATABASE_URL=mysql://db:db@0.0.0.0:32880/db?serverVersion=mariadb-10.3.13
```

## Commands

`bin/symfony-console`: Run `bin/console` in a Symfony project

`bin/symfony-serve`: Serve a Symfony project with the [Symfony binary](https://github.com/symfony/cli)
