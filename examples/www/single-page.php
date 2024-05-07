<?php
require_once("../../src/Loader.php");

$aurora = new Nlg\Aurora\Loader([
    'root'  => getcwd() . '/../',
    'views' => '/views',
    'cache' => '/cache',
]);

$aurora->setFiles([
    '/layout.html',
    '/components/header.html',
    '/components/footer.html',
    '/forms/forms.html',
    '/single-page.html',
]);

$aurora->setLanguageConstants([
    'hello world' => 'Hello World',
]);

$aurora->setVariables([
    'lang' => 'en',
    'title' => 'Aurora Single Page',
    'year' => date("Y")
]);

$aurora->createCache(true);

print $aurora->render("layout");