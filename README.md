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
MARIADB_PORT=32777
DATABASE_URL=mysql://db:db@0.0.0.0:$MARIADB_PORT/db?serverVersion=mariadb-10.3.13
```

## Commands

`bin/update-dotenv`: Update `.env.docker.local` in current directory.

`symfony/bin/console`: Run `bin/console` in a Symfony project

`symfony/cli/serve`: Serve a Symfony project with the [Symfony binary](https://github.com/symfony/cli)
