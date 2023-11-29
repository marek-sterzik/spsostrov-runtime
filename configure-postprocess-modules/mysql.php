<?php

use SPSOstrov\Runtime\Config;

Config::section("MySQL configuration");

Config::set("APP_DB_URL", "mysql://user:userpass@database:3306/thedatabase?serverVersion=5.7");
Config::addComposeFile("docker-compose/mysql.yml");
