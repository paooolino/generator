<?php

$config = require __DIR__ . '/config.php';

$APP_DIR_NAME = 'generated_app';

$APP_DIR  = __DIR__ . '/' . $APP_DIR_NAME;

$RECREATE = (($argv[1] ?? "") == "recreate");

if (!file_exists($APP_DIR . '/app')) { mkdir($APP_DIR . '/app', 0777, true); }
if (!file_exists($APP_DIR . '/app/Controller')) { mkdir($APP_DIR . '/app/Controller', 0777, true); }
if (!file_exists($APP_DIR . '/app/Middleware')) { mkdir($APP_DIR . '/app/Middleware', 0777, true); }
if (!file_exists($APP_DIR . '/app/Service')) { mkdir($APP_DIR . '/app/Service', 0777, true); }
if (!file_exists($APP_DIR . '/app/Vm')) { mkdir($APP_DIR . '/app/Vm', 0777, true); }
if (!file_exists($APP_DIR . '/htdocs')) { mkdir($APP_DIR . '/htdocs', 0777, true); }
if (!file_exists($APP_DIR . '/htdocs/assets')) { mkdir($APP_DIR . '/htdocs/assets', 0777, true); }
if (!file_exists($APP_DIR . '/htdocs/assets/css')) { mkdir($APP_DIR . '/htdocs/assets/css', 0777, true); }
if (!file_exists($APP_DIR . '/htdocs/assets/js')) { mkdir($APP_DIR . '/htdocs/assets/js', 0777, true); }
if (!file_exists($APP_DIR . '/htdocs/assets/images')) { mkdir($APP_DIR . '/htdocs/assets/images', 0777, true); }
if (!file_exists($APP_DIR . '/htdocs/assets/fonts')) { mkdir($APP_DIR . '/htdocs/assets/fonts', 0777, true); }
if (!file_exists($APP_DIR . '/templates')) { mkdir($APP_DIR . '/templates', 0777, true); }
if (!file_exists($APP_DIR . '/templates/partials')) { mkdir($APP_DIR . '/templates/partials', 0777, true); }

$populateTemplate = function($tpl, $data) {
  foreach ($data as $k => $v) {
    $tpl = str_replace("{{".$k."}}", $v, $tpl);
  }
  return $tpl;
};

if (isset($config["otherfiles"])) {
  foreach($config["otherfiles"] as $otherfile) {
    $config = array_merge($config, (require __DIR__ . '/' . $otherfile));
  }
  unset($config["otherfiles"]);
}


foreach($config as $groupname => $group) {
  if (is_callable($group)) {
    $config[$groupname] = $group();
  }
}


$routes_tpl = file_get_contents(__DIR__ . '/src_templates/routes.php.tpl');
$routes_group_tpl = file_get_contents(__DIR__ . '/src_templates/routes_group.tpl');
$routes_route_tpl = file_get_contents(__DIR__ . '/src_templates/routes_route.tpl');

$routes_group_source = implode("\r\n\r\n", array_map(function($group, $groupname) use($routes_group_tpl, $routes_route_tpl, $populateTemplate) {
    return $populateTemplate($routes_group_tpl, [
      "GROUP_NAME" => $groupname,
      "GROUP_MIDDLEWARES" => implode("", array_map(function($Middleware) {
        return '->add(WebApp\Middleware\\' . $Middleware . '::class)';
      }, $group["middlewares"])),
      "GROUP_ROUTES" => implode("\r\n", array_map(function($route, $routename) use($routes_route_tpl, $populateTemplate){
        return $populateTemplate($routes_route_tpl, [
          "METHOD" => $route["method"] ?? "get",
          "URL" => $route["url"],
          "ROUTE_NAME" => $routename,
          "CLASSNAME" => str_replace(" ", "", ucwords(strtolower(str_replace("_", " ", $routename))))
        ]);
      }, array_values($group["routes"]), array_keys($group["routes"])))
    ]);
  }, array_values($config), array_keys($config)
));

$routes_source = $populateTemplate($routes_tpl, [
  "GROUPS" => $routes_group_source
]);

file_put_contents($APP_DIR . '/app/routes.php', $routes_source);


$debug_infos = "";
$controller_tpl = file_get_contents(__DIR__ . '/src_templates/controller.tpl');
foreach($config as $groupname => $group) {
  foreach($group["routes"] as $routename => $route) {
    $controller_name = str_replace(" ", "", ucwords(strtolower(str_replace("_", " ", $routename))));

    $vm_calls = "";
    $vars = "";
    
    if (isset($route["content"]) || isset($route["actions"])) {
      $infos = retrieve_vm_content_infos(($route["content"] ?? []), ($route["actions"] ?? []));
      $vm_calls = $infos["vm_calls"];
      $vars = $infos["vars"];
    }

    $source_view = "";
    if (isset($route["layout"])) {
      $source_view = '    return ' . '$this->view->render($response, "' . $route["layout"] . '", [
        ' . $vars . '
      ]);';
    } 
    if (isset($route["responseType"]) && $route["responseType"] == "json") {
      $source_view = '    return ' . '$response->withJson([' . $vars . ']);';
    }

    $source_redirect = "";
    if (isset($route["redirect_to"])) {

      if (is_array($route["redirect_to"])) {

        if (isset($route["redirect_to"]["pageByName"])) {
          $source_redirect = '    return $response->withRedirect($this->app->pageByName("' . $route["redirect_to"]["pageByName"] . '"));';
        } else {
          $params = array_map(
            function($k, $v) {
              return '"' . $k . '" => ' . $v;
            }, 
            array_keys($route["redirect_to"]["params"]), 
            array_values($route["redirect_to"]["params"])
          );
          $source_redirect = '    return $response->withRedirect($this->app->router->urlFor("' . $route["redirect_to"]["route"] . '", [' . implode("," , $params) . ']));';
        }
      } else {
        if ($route["redirect_to"] == '<REFERRER>'){
          $source_redirect = '
    $header = $request->getHeader("HTTP_REFERER");
    if (empty($header))
      $header = $request->getHeader("Referer");
    if (empty($header))
      die();
  
    $url = array_shift($header);
      
    return $response->withRedirect($url); 
          ';
        } else {
          $source_redirect = '    return $response->withRedirect($this->app->router->urlFor("' . $route["redirect_to"] . '"));';
        }
      }


    }

    $debug_infos = '    $this->app->add_debug_info("[route name] ' . $routename . '");' . "\r\n";
    if (isset($route["content"])) {
      foreach ($route["content"] as $k => $v) {
        $debug_infos .= '    $this->app->add_debug_info("[template placeholder] ' . $k . ' [defined in] ' . $v . '");' . "\r\n";
      }
    }
    
    $controller_source = $populateTemplate($controller_tpl, [
      "CONTROLLER_NAME" => $controller_name,
      "RETURN_VIEW" => $source_view,
      "RETURN_REDIRECT" => $source_redirect,
      "VM_CALLS" => $vm_calls,
      "DEBUG_INFOS" => $debug_infos
    ]);

    file_put_contents($APP_DIR . '/app/Controller/' . $controller_name . '.php', $controller_source);
  }
}

write_file_if_not_exists($APP_DIR . '/composer.json', __DIR__ . '/src_files/composer.json.file');
write_file_if_not_exists($APP_DIR . '/settings.dev.php', __DIR__ . '/src_files/settings.dev.php.file');
write_file_if_not_exists($APP_DIR . '/settings.prod.php', __DIR__ . '/src_files/settings.prod.php.file');
write_file_if_not_exists($APP_DIR . '/environment', __DIR__ . '/src_files/environment.file');
write_file_if_not_exists($APP_DIR . '/htdocs/index.php', __DIR__ . '/src_files/index.php.file');
write_file_if_not_exists($APP_DIR . '/htdocs/.htaccess', __DIR__ . '/src_files/.htaccess.file');
write_file_if_not_exists($APP_DIR . '/app/middleware.php', __DIR__ . '/src_files/middleware.php.file');
write_file_if_not_exists($APP_DIR . '/app/dependencies.php', __DIR__ . '/src_files/dependencies.php.file');
write_file_if_not_exists($APP_DIR . '/app/Middleware/AppInit.php', __DIR__ . '/src_files/AppInit.php.file');
write_file_if_not_exists($APP_DIR . '/app/Middleware/ErrorpageMiddleware.php', __DIR__ . '/src_files/ErrorpageMiddleware.php.file');
write_file_if_not_exists($APP_DIR . '/app/Service/App.php', __DIR__ . '/src_files/App.php.file');
write_file_if_not_exists($APP_DIR . '/app/Service/Db.php', __DIR__ . '/src_files/Db.php.file');
write_file_if_not_exists($APP_DIR . '/app/Service/VmApi.php', __DIR__ . '/src_files/VmApi.php.file');
write_file_if_not_exists($APP_DIR . '/app/Service/Utils.php', __DIR__ . '/src_files/Utils.php.file');
write_file_if_not_exists($APP_DIR . '/app/Service/Html.php', __DIR__ . '/src_files/Html.php.file');
write_file_if_not_exists($APP_DIR . '/app/Service/Upload.php', __DIR__ . '/src_files/Upload.php.file');
write_file_if_not_exists($APP_DIR . '/app/Service/Mailer.php', __DIR__ . '/src_files/Mailer.php.file');
write_file_if_not_exists($APP_DIR . '/app/Vm/home.php', __DIR__ . '/src_files/home.php.file');
write_file_if_not_exists($APP_DIR . '/templates/layout.php', __DIR__ . '/src_files/layout.php.file');
write_file_if_not_exists($APP_DIR . '/templates/layout-backoffice.php', __DIR__ . '/src_files/layout-backoffice.php.file');
write_file_if_not_exists($APP_DIR . '/templates/partials/header.php', __DIR__ . '/src_files/header.php.file');
write_file_if_not_exists($APP_DIR . '/templates/partials/footer.php', __DIR__ . '/src_files/footer.php.file');

function load_config() {
  return [];
}

function write_file_if_not_exists($destination_path, $source_path) {
  global $RECREATE;
  if (!file_exists($destination_path) || $RECREATE) {
    copy($source_path, $destination_path);
  }
}

function retrieve_vm_content_infos($content, $actions) {

  $call_names = [];
  $content_values = array_values($content);
  foreach ($content_values as $cv) {
    $call_names[] = explode(" ", $cv)[0]; 
  }
  $call_names  = array_unique($call_names);

  //
  $call_sources_content = array_map(function($call_name) {
    return "    " . '$' . str_replace("/", "_", $call_name) . ' = $this->vm->call("' . $call_name . '", [], $args, $get, $post, $files, $response);';
  }, $call_names);

  // 
  $call_sources_actions = array_map(function($action) {
    $call_name = explode(" ", $action)[0];
    $action_name = explode(" ", $action)[1];

    // 
    $check_if_return_immediatly = "    " . 'if ($response->hasHeader("Location")) return $response;';
    return "    " . '$' . str_replace("/", "_", $call_name) . ' = $this->vm->call("' . $call_name . '", ["' . $action_name . '"], $args, $get, $post, $files, $response);' . "\r\n"
         . "    " . '$response = ' . '$' . str_replace("/", "_", $call_name) . '["actions"]["' . $action_name . '"];' . "\r\n"
         . $check_if_return_immediatly;
  }, $actions);

  // 
  $vars_sources = array_map(function($k, $v) {
    $parts = explode(" ", $v);
    $call_name = $parts[0];
    $call_part = $parts[1];
    return "      " . '"' . $k . '" => $' . str_replace("/", "_", $call_name) . '["widgets"]["' . $call_part . '"]';
  }, array_keys($content), array_values($content));

  return [
    "vm_calls" => implode("\r\n", array_merge($call_sources_content, $call_sources_actions)),
    "vars" => implode(",\r\n", $vars_sources)
  ];
}
