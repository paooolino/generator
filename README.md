# generator
A PHP script that generates a Slim Framework application source code starting from configuration files 

## usage

1. clone this repository
2. php generator/generator.php
3. a new generated_app folder is created. cd generatet_app
4. compoer install to install dependencies.
5. configure local servet to point to the generated_app/htdocs folder through generator-app.local domain
6. create a lcoal database and edit database name in settings.dev app
7. point browser to http://generator-app.local/

## configuration

The main configuration file is generator/config.php.

This must be a PHP script that returns a PHP associative array with the following format:

    <?php
    
    return [
      "otherfiles" => [
        <a list of other configuration files to load>
      ],

      "<group name>" => [
        "middlewares" => [<a list of middlewares attached to this group>],
        "routes" => [

          // example of a "VIEW" route
          "<route name>" => [
            "url" => "<route slug>",
            "content" => [
              <a key-value list of contents to load in the page>
            ],
            "layout" => "<the layout file name>"
          ],

          // example of an "ACTION" route
          "<route name" => [
            "url" => "<route slug>",
            "method" => "post",  // optional
            "actions" => [
              <a key-value list of actions to perform>
            ],
            "redirect_to" => <redirection configuration>
          ]
        
        ] // end routes
      ] // end group
    ];

### Routes

There are two types of routes

1. VIEW routes: they just generate a page using a template file.
2. ACTION routes: they executes code that result in a side effect (write to database; write to file; send mail; set cookie). Action routes always redirect to another VIEW route. 

As best practice, we recommend to append _ACTION to the route name so you can instantly argue its type. For example:

- "LOGIN" should be the route name of the page displaying the login form
- "LOGIN_ACTION" should contain the code that checks for the login credentials.

### VIEW routes content

The content keys are variables that will be available in the layout file.
The content values are strings, divided in two parts by a single spance. The first part is the View-Model file in which you can find a collection of widgets. The second part is the widget name. What's a View-Modell file? read below.

Example

    "content" => [
      "pre_content" => "front/home pre_content",
      "content" => "front/home content",
      "sidebar_content" => "front/teams standings"
    ]

### ACTION routes actions

In this case there is no layout and no variables. The "actions" array is just a list of strings divided in two parts. The meaning is the same as for the VIEW routes. The string will reference an action defined in the View-Model file.

    "actions" => [
      "front/usermanagement login"
    ],
        

### View-Model files

These files are useful for combining data coming from database (or other sources) and HTML code to produce widgets ready to display in a page. You can define actions as well.

    <?php

        return [
          "calls" => [
            [
              "sql" => "<sql query>",
              "data" => function($args) {
                  return [...];
              }
            ],
            ...
          ],
        
          "widgets" => [
            "<widget name>" => function($data, $c) {
                $html = ...
                return $html;
            },
          ],
          
          "actions" => [
            "<action name>" => function($data, $c, $args, $get, $post, $files, $response) {
                ...
                return $response;
            }
          ]
        ];

### Services

The generated Slim Application defines some default services and others in which you can write the custom code for your App:

#### App

Contains generic functions, useful in all projects.

- getStatus($response, $data)
- setStatus($attr="")

- add_debug_info($s)

- encrypt
- decrypt

- populateVars($s, $vars)

- setErrors($errors_text)

- getTemplateUrl() used to link the assets in the templates

#### Db

Base functions

- select($sql, $data, $cache=true)
- insert($sql, $data)
- update($sql, $data)
- delete($sql, $data)

Cache management

- empty_cache()

Helpers

- selectAll($table)
- selectById/selectOne($table, $id)
- selectBy($table, $field, $value)
- table_insert($table, $fields, $autoAddFields=false)
- table_update($table, $id, $fields, $autoAddFields=false)

Others

- lastInsertId()
- rawQuery($query, $data, $cache=true)
 
#### Utils

Custom function useful for the current web application.

### Html

Html components builder, specific for the current web application.

## settings

The _path variables represents the internal server folder;
The _dir variables represents the internal server folder relative to the webroot_path;
The _url variables represents the http path to reach the resource

- app_version
- app_url
- webroot_path (eg. __DIR__ or __DIR__ . '/htdocs')
- uploads_dir (relative to webroot: eg. '/uploads')
- template_dir (relative to webroot: eg. '/templates/default')
- debug_infos
- autoAddFields

## AppInit middleware

This middleware is attached to the Slim Application and populates the following variables:

app->settings
app->router



