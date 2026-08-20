<?php

declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Controllers\ApiController;
use App\Controllers\AuthController;
use App\Controllers\CampaignController;
use App\Controllers\CategoryController;
use App\Controllers\PageController;
use App\Lib\Auth;
use App\Lib\Router;

$router = new Router();

// --- Public / landing ---
$router->get('/', function () {
    Auth::check() ? redirect('/dashboard') : redirect('/login');
});

// --- Auth ---
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/register', [AuthController::class, 'showRegister']);
$router->post('/register', [AuthController::class, 'register']);
$router->post('/logout', [AuthController::class, 'logout']);

// --- Campaigns ---
$router->get('/dashboard', [CampaignController::class, 'dashboard']);
$router->post('/campaigns', [CampaignController::class, 'create']);
$router->post('/campaigns/join', [CampaignController::class, 'join']);
$router->get('/campaign/{id}', [CampaignController::class, 'show']);

// --- Categories ---
$router->post('/campaign/{id}/categories', [CategoryController::class, 'store']);
$router->post('/campaign/{id}/categories/{cid}/rename', [CategoryController::class, 'rename']);
$router->post('/campaign/{id}/categories/{cid}/delete', [CategoryController::class, 'delete']);

// --- Pages ---
$router->get('/campaign/{id}/new', [PageController::class, 'createForm']);
$router->post('/campaign/{id}/pages', [PageController::class, 'store']);
$router->get('/campaign/{id}/page/{slug}', [PageController::class, 'show']);
$router->get('/campaign/{id}/page/{slug}/edit', [PageController::class, 'editForm']);
$router->post('/campaign/{id}/page/{slug}/edit', [PageController::class, 'update']);
$router->post('/campaign/{id}/page/{slug}/delete', [PageController::class, 'delete']);
$router->get('/campaign/{id}/page/{slug}/history', [PageController::class, 'history']);
$router->post('/campaign/{id}/page/{slug}/restore', [PageController::class, 'restore']);

// --- API ---
$router->get('/api/campaign/{id}/search', [ApiController::class, 'search']);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $path);
