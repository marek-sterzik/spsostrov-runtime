<?php

use SPSOstrov\Runtime\Config;

Config::section("Webserver configuration");

Config::question("APP_PORT", "On which port should the application webserver listen?", 80, "port");
