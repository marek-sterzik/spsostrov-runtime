<?php

use SPSOstrov\Runtime\Config;

Config::section("MySQL configuration");

Config::addComposeFile("docker-compose/mysql.yml");
