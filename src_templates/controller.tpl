<?php
namespace WebApp\Controller;

use Psr\Container\ContainerInterface;

class {{CONTROLLER_NAME}} {
  private $view;
  private $vm;
  private $app;

  public function __construct(ContainerInterface $c) {
    $this->view = $c->get("view");
    $this->vm = $c->get("vm");
    $this->app = $c->get("app");
  }

  public function __invoke($request, $response, $args) {
    $get = $request->getQueryParams();
    $post = $request->getParsedBody();
    $files = $request->getUploadedFiles();

{{DEBUG_INFOS}}

{{VM_CALLS}}

{{RETURN_VIEW}}

{{RETURN_REDIRECT}}
  }
}