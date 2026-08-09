<?php
// config/secrets.php
declare(strict_types=1);

$SECRETS = [];

/*
  TAJNÉ ÚDAJE – NIKDY NEPATŘÍ DO GITU
*/

$SECRETS['db']['local'] = [
    'host' => '127.0.0.1',
    'user' => 'root',
    'pass' => 'Halama_1965',
    'name' => 'comeback',
];

$SECRETS['db']['server'] = [
    'host' => 'localhost',
    'user' => 'root-mysql',
    'pass' => 'bnW44uKkby@@5FBY',
    'name' => 'comeback_system',
];

$SECRETS['db']['codex'] = [
    'host' => '127.0.0.1',
    'user' => 'codex',
    'pass' => '1234',
    'name' => 'comeback',
];


/*
  RESTIA API
*/

$SECRETS['restia'] = [
    'refresh_token' => 'AGhWYcnKf+H4hFrmpBXzoETQQfBTt4ie+UOu96aNRd/cE5TXzwENdVw3TrUv89lyW6aG+AdnM3dJpdsTugti0YWtWUGSDJGFC8TjD7N1L8LeFfaUsnz2eLpIB6wRZju5HvdNo6ckny+Ng2jk+WixUnVAwF0hnbEa801U1tp1RcPj1C/Tbxck52d5/ZYKrySjAwWjt6vRutF9hc/jbNaQrZW2+w+ounAfffMxV53xr3LTPMXgLNMuufuycAyJqfZnqVFTYB49pLjF7QDFhh2MzP6m7jRCaor99kMnVa6Ntdf7a2hA0KCHRUJ24gIT7Uv51iM20ei4ey/39I2nOIC8mxFIYDQ3QIGLRjEssW6/wWDzp7cWBvFMgzGT10KS/LArvZ5mDd/2HIIaz7mH4rJPriXw/HONXf71RRB/2q0QGKPHhfJXm1NbViUlpjZskkZNbNNYUloXSk6ghU46YCoekBf9GO35Uan0wypHKcJwFM/UrIFrwHQbKtNfFaggMibISYkJo756lWWowV+rg0nI8NM+zpOAXVKo56MFTZGDAJhjrl7nwKLKH7G7qfXQAjma0VrCPA2lwkKs4FFCD78ozJuvK6+MxRJ5LgVkHkf+VHfyeronyhPtAUNJG+RGzj/LAuuGPqkcH7uYEX+kF7d4yO5XRNtPUhIo7lJqIG3jjP4=',
];

/*
  SMĚNY API
*/

$SECRETS['smeny'] = [
    'email' => 'maniiax3d@gmail.com',
    'heslo' => 'Halama_1965',
];

$SECRETS['mail']['hr'] = [
    'host' => 'tan09.vas-server.cz',
    'port' => 465,
    'secure' => 'ssl',
    'user' => 'hr@comebacks.cz',
    'pass' => 'Hr_heslo_2026',
    'from' => 'hr@comebacks.cz',
    'from_name' => 'Pizza Comeback HR',
];
