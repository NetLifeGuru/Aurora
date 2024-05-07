<?php

require_once "../../src/Loader.php";

$aurora = new Nlg\Aurora\Loader([
    'root'  =>  getcwd() . '/../',
    'views' => '/views',
    'cache' => '/cache',
]);

$aurora->setRoutes([
    '*' => [
        '/layout.html',
        '/components/header.html',
        '/components/footer.html',
        '/forms/forms.html',
    ],
    '' => [
        '/pages/index.html',
    ],
    'examples' => [
        '/pages/examples.html',
        '/pages/docs/forms.html',
        '/pages/docs/usage.html',
        '/pages/docs/storage.html',
        '/pages/docs/templates.html',
        '/pages/docs/variables.html',
        '/pages/docs/language.html',
        '/pages/docs/cache.html',
        '/pages/docs/router.html',
        '/pages/docs/using.html',
        '/pages/docs/blocks.html',
        '/pages/docs/usingVariables.html',
        '/pages/docs/calculations.html',
        '/pages/docs/include.html',
        '/pages/docs/import.html',
        '/pages/docs/resources.html',
        '/pages/docs/controlStructures.html',
        '/pages/docs/ternaryOperator.html',
        '/pages/docs/macros.html',
        '/pages/docs/customMacros.html',
        '/pages/docs/forms.html',
        '/pages/docs/customForms.html',
        '/pages/docs/customPHPForms.html',
        '/pages/docs/curlyBrackets.html',
    ]
]);

$aurora->setLanguageConstants([
    'hello world' => 'Hello World',
]);

$aurora->setVariables([
    'lang'  =>  'en',
    'title' =>  'Aurora Example',
    'year'  =>  date("Y")
]);

$aurora->createCache(true);

print $aurora->render("layout");

