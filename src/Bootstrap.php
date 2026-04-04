<?php

declare(strict_types=1);

namespace App;

use App\Config\Env;
use App\Controllers\AuthController;
use App\Controllers\BillingController;
use App\Controllers\ConnectionController;
use App\Controllers\CustomerController;
use App\Controllers\DashboardController;
use App\Controllers\HealthController;
use App\Controllers\PackageController;
use App\Controllers\ProductController;
use App\Controllers\ReportController;
use App\Controllers\TopologyController;
use App\Controllers\UserController;
use App\Controllers\WebController;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Services\AuthService;

final class Bootstrap
{
    public function run(): void
    {
        $root = dirname(__DIR__);
        Env::load($root);

        if (Env::bool('APP_DEBUG', true)) {
            ini_set('display_errors', '1');
            error_reporting(E_ALL);
        }

        $router = new Router();

        $authService = new AuthService();
        $authService->ensureDefaultAdmin(
            (string) Env::get('DEFAULT_ADMIN_USERNAME', 'admin'),
            (string) Env::get('DEFAULT_ADMIN_PASSWORD', 'admin123'),
            (string) Env::get('DEFAULT_ADMIN_NAME', 'Administrator'),
        );

        $authController = new AuthController($authService);
        $healthController = new HealthController();
        $webController = new WebController();
        $customerController = new CustomerController();
        $productController = new ProductController();
        $topologyController = new TopologyController();
        $packageController = new PackageController();
        $connectionController = new ConnectionController();
        $billingController = new BillingController();
        $dashboardController = new DashboardController();
        $userController = new UserController();
        $reportController = new ReportController();

        $guardWeb = function (callable $handler) use ($authService): callable {
            return function (Request $request) use ($handler, $authService): void {
                if (!$authService->isAuthenticated()) {
                    Response::redirect('/login');
                    return;
                }

                $handler($request);
            };
        };

        $guardApi = function (callable $handler) use ($authService): callable {
            return function (Request $request) use ($handler, $authService): void {
                if (!$authService->isAuthenticated()) {
                    Response::json([
                        'status' => 'error',
                        'message' => 'Unauthorized',
                    ], 401);
                    return;
                }

                $handler($request);
            };
        };

        $router->add('GET', '/login', fn (Request $request) => $authController->loginPage($request));
        $router->add('POST', '/login', fn (Request $request) => $authController->login($request));
        $router->add('GET', '/logout', fn (Request $request) => $authController->logout($request));

        $router->add('GET', '/', $guardWeb(fn (Request $request) => $webController->home($request)));
        $router->add('GET', '/customers', $guardWeb(fn (Request $request) => $webController->customers($request)));
        $router->add('GET', '/customers/list', $guardWeb(fn (Request $request) => $webController->customerList($request)));
        $router->add('GET', '/topology', $guardWeb(fn (Request $request) => $webController->topology($request)));
        $router->add('GET', '/packages', $guardWeb(fn (Request $request) => $webController->packages($request)));
        $router->add('GET', '/products', $guardWeb(fn (Request $request) => $webController->products($request)));
        $router->add('GET', '/connections', $guardWeb(fn (Request $request) => $webController->connections($request)));
        $router->add('GET', '/billing', $guardWeb(fn (Request $request) => $webController->billing($request)));
        $router->add('GET', '/reports', $guardWeb(fn (Request $request) => $webController->reports($request)));
        $router->add('GET', '/payments', $guardWeb(fn (Request $request) => $webController->billing($request)));
        $router->add('GET', '/settings', $guardWeb(fn (Request $request) => $webController->settings($request)));
        $router->add('GET', '/users', $guardWeb(fn (Request $request) => $webController->users($request)));

        $router->add('GET', '/api/health', fn (Request $request) => $healthController->index($request));
        $router->add('GET', '/api/auth/me', $guardApi(fn (Request $request) => $authController->me($request)));

        $router->add('GET', '/api/customers', $guardApi(fn (Request $request) => $customerController->index($request)));
        $router->add('GET', '/api/customers/check-unique', $guardApi(fn (Request $request) => $customerController->checkUnique($request)));
        $router->add('POST', '/api/customers', $guardApi(fn (Request $request) => $customerController->store($request)));
        $router->add('POST', '/api/customers/with-connection', $guardApi(fn (Request $request) => $customerController->storeWithConnection($request)));
        $router->add('POST', '/api/customers/update', $guardApi(fn (Request $request) => $customerController->update($request)));
        $router->add('POST', '/api/customers/delete', $guardApi(fn (Request $request) => $customerController->delete($request)));

        $router->add('GET', '/api/products', $guardApi(fn (Request $request) => $productController->index($request)));
        $router->add('POST', '/api/products', $guardApi(fn (Request $request) => $productController->store($request)));
        $router->add('POST', '/api/products/update', $guardApi(fn (Request $request) => $productController->update($request)));
        $router->add('POST', '/api/products/delete', $guardApi(fn (Request $request) => $productController->delete($request)));

        $router->add('GET', '/api/users', $guardApi(fn (Request $request) => $userController->index($request)));
        $router->add('POST', '/api/users', $guardApi(fn (Request $request) => $userController->store($request)));

        $router->add('GET', '/api/zones', $guardApi(fn (Request $request) => $topologyController->listZones($request)));
        $router->add('POST', '/api/zones', $guardApi(fn (Request $request) => $topologyController->createZone($request)));
        $router->add('POST', '/api/zones/update', $guardApi(fn (Request $request) => $topologyController->updateZone($request)));
        $router->add('POST', '/api/zones/delete', $guardApi(fn (Request $request) => $topologyController->deleteZone($request)));
        $router->add('GET', '/api/areas', $guardApi(fn (Request $request) => $topologyController->listAreas($request)));
        $router->add('POST', '/api/areas', $guardApi(fn (Request $request) => $topologyController->createArea($request)));
        $router->add('POST', '/api/areas/update', $guardApi(fn (Request $request) => $topologyController->updateArea($request)));
        $router->add('POST', '/api/areas/delete', $guardApi(fn (Request $request) => $topologyController->deleteArea($request)));
        $router->add('GET', '/api/line-sources', $guardApi(fn (Request $request) => $topologyController->listLineSources($request)));
        $router->add('POST', '/api/line-sources', $guardApi(fn (Request $request) => $topologyController->createLineSource($request)));
        $router->add('POST', '/api/line-sources/update', $guardApi(fn (Request $request) => $topologyController->updateLineSource($request)));
        $router->add('POST', '/api/line-sources/delete', $guardApi(fn (Request $request) => $topologyController->deleteLineSource($request)));
        $router->add('GET', '/api/distribution-boxes', $guardApi(fn (Request $request) => $topologyController->listDistributionBoxes($request)));
        $router->add('POST', '/api/distribution-boxes', $guardApi(fn (Request $request) => $topologyController->createDistributionBox($request)));
        $router->add('POST', '/api/distribution-boxes/update', $guardApi(fn (Request $request) => $topologyController->updateDistributionBox($request)));
        $router->add('POST', '/api/distribution-boxes/delete', $guardApi(fn (Request $request) => $topologyController->deleteDistributionBox($request)));

        $router->add('GET', '/api/packages', $guardApi(fn (Request $request) => $packageController->index($request)));
        $router->add('POST', '/api/packages', $guardApi(fn (Request $request) => $packageController->store($request)));
        $router->add('POST', '/api/packages/update', $guardApi(fn (Request $request) => $packageController->update($request)));
        $router->add('POST', '/api/packages/delete', $guardApi(fn (Request $request) => $packageController->delete($request)));

        $router->add('GET', '/api/connections', $guardApi(fn (Request $request) => $connectionController->index($request)));
        $router->add('POST', '/api/connections', $guardApi(fn (Request $request) => $connectionController->store($request)));
        $router->add('POST', '/api/connections/update', $guardApi(fn (Request $request) => $connectionController->update($request)));
        $router->add('POST', '/api/connections/delete', $guardApi(fn (Request $request) => $connectionController->delete($request)));
        $router->add('GET', '/print/connection-summary', $guardWeb(fn (Request $request) => $connectionController->printSummary($request)));
        $router->add('GET', '/print/customer-profile', $guardWeb(fn (Request $request) => $customerController->printProfile($request)));

        $router->add('POST', '/api/connections/preview-cost', $guardApi(fn (Request $request) => $billingController->previewConnectionCost($request)));
        $router->add('POST', '/api/billing/generate-monthly', $guardApi(fn (Request $request) => $billingController->generateMonthlyBills($request)));
        $router->add('GET', '/api/bills', $guardApi(fn (Request $request) => $billingController->listBills($request)));
        $router->add('POST', '/api/payments', $guardApi(fn (Request $request) => $billingController->postPayment($request)));
        $router->add('GET', '/api/payments', $guardApi(fn (Request $request) => $billingController->listPayments($request)));

        $router->add('GET', '/api/dashboard/summary', $guardApi(fn (Request $request) => $dashboardController->summary($request)));
        $router->add('GET', '/api/reports/overview', $guardApi(fn (Request $request) => $reportController->overview($request)));
        $router->add('GET', '/api/reports/income-expense/print', $guardWeb(fn (Request $request) => $reportController->incomeExpensePrint($request)));
        $router->add('GET', '/api/reports/income-expense/csv', $guardApi(fn (Request $request) => $reportController->incomeExpenseCsv($request)));
        $router->add('GET', '/api/reports/transactions/print', $guardWeb(fn (Request $request) => $reportController->transactionsPrint($request)));
        $router->add('GET', '/api/reports/transactions/csv', $guardApi(fn (Request $request) => $reportController->transactionsCsv($request)));
        $router->add('GET', '/api/reports/bills/csv', $guardApi(fn (Request $request) => $reportController->billsCsv($request)));
        $router->add('GET', '/api/reports/payments/csv', $guardApi(fn (Request $request) => $reportController->paymentsCsv($request)));
        $router->add('GET', '/api/reports/customers/csv', $guardApi(fn (Request $request) => $reportController->customersCsv($request)));
        $router->add('GET', '/api/reports/customers/print', $guardWeb(fn (Request $request) => $reportController->customersPrint($request)));
        $router->add('GET', '/api/reports/inventory/csv', $guardApi(fn (Request $request) => $reportController->inventoryCsv($request)));
        $router->add('GET', '/api/reports/connections/csv', $guardApi(fn (Request $request) => $reportController->connectionsCsv($request)));

        try {
            $router->dispatch(Request::capture());
        } catch (\Throwable $exception) {
            Response::json([
                'status' => 'error',
                'message' => 'Unexpected server error',
                'error' => Env::bool('APP_DEBUG', true) ? $exception->getMessage() : null,
            ], 500);
        }
    }
}
