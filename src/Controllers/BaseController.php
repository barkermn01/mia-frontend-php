<?php

namespace Marti\Frontend\Controllers;

use Mia\SDK\MiaClient;
use Marti\Frontend\View;
use Marti\Frontend\HtmlResources;

abstract class BaseController
{
    protected $client;
    protected $view;
    protected $config;
    protected $adminPath;

    public function __construct(MiaClient $client, View $view, array $config, string $adminPath)
    {
        $this->client = $client;
        $this->view = $view;
        $this->config = $config;
        $this->adminPath = $adminPath;
    }

    protected function show404(): void
    {
        http_response_code(404);
        $content = $this->view->render('404');
        echo $this->view->renderLayout('admin-layout', $content, [
            'title' => 'Page Not Found - Admin Panel',
            'user' => $_SESSION['customer'],
            'adminPath' => $this->adminPath
        ]);
    }

    protected function showError(string $message): void
    {
        $content = $this->view->render('error', ['message' => $message]);
        echo $this->view->renderLayout('admin-layout', $content, [
            'title' => 'Error - Admin Panel',
            'user' => $_SESSION['customer'],
            'adminPath' => $this->adminPath
        ]);
    }

    protected function redirect(string $path, ?string $message = null, bool $isError = false): void
    {
        $url = $this->adminPath . $path;
        if ($message) {
            $param = $isError ? 'error' : 'success';
            $url .= (strpos($url, '?') !== false ? '&' : '?') . $param . '=' . urlencode($message);
        }
        header("Location: {$url}");
        exit;
    }

    protected function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
