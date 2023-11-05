<?php

use SPSOstrov\Runtime\Config;

function testDockerCommand(string $command): bool
{
    $ret = 0;
    $data = system(sprintf("%s > /dev/null 2>&1", $command), $ret);
    if ($data === false) {
        return false;
    }
    return ($ret === 0) ? true : false;
}


$dockerComposeVersions = [
    2 => "docker compose",
    1 => "docker-compose",
];

$dockerComposeVersion = Config::get("SPSO_DOCKER_COMPOSE_VERSION");

if (preg_match('/^[0-9]+$/', $dockerComposeVersion)) {
    $dockerComposeVersion = (int)$dockerComposeVersion;
    if (!isset($dockerComposeVersions[$dockerComposeVersion])) {
        $dockerComposeVersion = null;
    }
}

if ($dockerComposeVersion !== null) {
    $val = $dockerComposeVersions[$dockerComposeVersion];
    unset($dockerComposeVersions[$dockerComposeVersion]);
    $dockerComposeVersions = array_reverse($dockerComposeVersions, true);
    $dockerComposeVersions[$dockerComposeVersion] = $val;
    $dockerComposeVersions = array_reverse($dockerComposeVersions, true);
}

$dockerComposeVersion = null;

foreach ($dockerComposeVersions as $v => $cmd) {
    if (testDockerCommand($cmd)) {
        $dockerComposeVersion = $v;
        break;
    }
}
if ($dockerComposeVersion === null) {
    Config::error("docker-compose not found, please install docker-compose");
    exit(1);
}

Config::set("SPSO_USER_UID", getmyuid());
Config::set("SPSO_USER_GID", getmygid());
Config::set("SPSO_DOCKER_COMPOSE_VERSION", $dockerComposeVersion);
