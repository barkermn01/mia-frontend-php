<?php

namespace Marti\Frontend\Controllers;

class ShippingController extends BaseController
{
    public function index(): void
    {
        try {
            $methods = $this->client->shipping->listMethodsAdmin();
            
            $content = $this->view->render('shipping', [
                'methods' => $methods['items'] ?? [],
                'total' => $methods['total'] ?? 0,
                'adminPath' => $this->adminPath
            ]);
            
            echo $this->view->renderLayout('admin-layout', $content, [
                'title' => 'Shipping Methods - Admin Panel',
                'user' => $_SESSION['customer'],
                'adminPath' => $this->adminPath
            ]);
        } catch (\Exception $e) {
            $this->showError("Failed to load shipping methods: " . $e->getMessage());
        }
    }

    public function showAdd(): void
    {
        try {
            $content = $this->view->render('shipping-form', [
                'method' => null,
                'isEdit' => false,
                'adminPath' => $this->adminPath
            ]);
            
            echo $this->view->renderLayout('admin-layout', $content, [
                'title' => 'Add Shipping Method - Admin Panel',
                'user' => $_SESSION['customer'],
                'adminPath' => $this->adminPath
            ]);
        } catch (\Exception $e) {
            $this->showError("Failed to load add shipping method form: " . $e->getMessage());
        }
    }

    public function showEdit(): void
    {
        $methodId = $_GET['id'] ?? '';
        if (!$methodId) {
            $this->showError("Shipping method ID is required");
            return;
        }

        try {
            $method = $this->client->shipping->getMethod($methodId);
            
            $content = $this->view->render('shipping-form', [
                'method' => $method,
                'isEdit' => true,
                'adminPath' => $this->adminPath
            ]);
            
            echo $this->view->renderLayout('admin-layout', $content, [
                'title' => 'Edit Shipping Method - Admin Panel',
                'user' => $_SESSION['customer'],
                'adminPath' => $this->adminPath
            ]);
        } catch (\Exception $e) {
            $this->showError("Failed to load shipping method: " . $e->getMessage());
        }
    }

    public function handleAdd(): void
    {
        try {
            $data = [
                'name' => $_POST['name'] ?? '',
                'provider' => $_POST['provider'] ?? '',
                'deliveryTime' => $_POST['delivery_time'] ?? '',
                'maxWeightKg' => !empty($_POST['max_weight']) ? (float)$_POST['max_weight'] : null,
                'enabled' => isset($_POST['enabled']) && $_POST['enabled'] === '1'
            ];

            if (empty($data['name'])) {
                throw new \Exception('Shipping method name is required');
            }

            // Handle regions
            $regions = [];
            if (!empty($_POST['regions'])) {
                $regionsData = json_decode($_POST['regions'], true);
                if (is_array($regionsData)) {
                    foreach ($regionsData as $region) {
                        if (!empty($region['countryCode'])) {
                            $regionData = [
                                'countryCode' => $region['countryCode'],
                                'countryName' => $region['countryName'] ?? ''
                            ];
                            
                            // Check if this is Classic mode (weightBrackets) or Modern mode (baseCost)
                            if (isset($region['weightBrackets']) && is_array($region['weightBrackets'])) {
                                // Classic mode: weight brackets
                                $regionData['weightBrackets'] = $region['weightBrackets'];
                            } elseif (isset($region['baseCost'])) {
                                // Modern mode: base cost + cost per kg
                                $regionData['baseCost'] = (int)$region['baseCost'];
                                $regionData['costPerKg'] = !empty($region['costPerKg']) ? (int)$region['costPerKg'] : 0;
                            } else {
                                // Skip invalid regions
                                continue;
                            }
                            
                            // Free shipping threshold (both modes)
                            if (!empty($region['freeShippingThreshold'])) {
                                $regionData['freeShippingThreshold'] = (int)$region['freeShippingThreshold'];
                            }
                            
                            $regions[] = $regionData;
                        }
                    }
                }
            }

            if (!empty($regions)) {
                $data['regions'] = $regions;
            }

            $this->client->shipping->createMethod($data);
            $this->redirect('/shipping', 'Shipping method created successfully');
        } catch (\Exception $e) {
            $content = $this->view->render('shipping-form', [
                'method' => $_POST,
                'isEdit' => false,
                'error' => $e->getMessage(),
                'adminPath' => $this->adminPath
            ]);
            
            echo $this->view->renderLayout('admin-layout', $content, [
                'title' => 'Add Shipping Method - Admin Panel',
                'user' => $_SESSION['customer'],
                'adminPath' => $this->adminPath
            ]);
        }
    }

    public function handleEdit(): void
    {
        $methodId = $_POST['method_id'] ?? '';
        if (!$methodId) {
            $this->showError("Shipping method ID is required");
            return;
        }

        try {
            $data = [
                'name' => $_POST['name'] ?? '',
                'provider' => $_POST['provider'] ?? '',
                'deliveryTime' => $_POST['delivery_time'] ?? '',
                'maxWeightKg' => !empty($_POST['max_weight']) ? (float)$_POST['max_weight'] : null,
                'enabled' => isset($_POST['enabled']) && $_POST['enabled'] === '1'
            ];

            if (empty($data['name'])) {
                throw new \Exception('Shipping method name is required');
            }

            $this->client->shipping->updateMethod($methodId, $data);

            // Handle regions
            if (!empty($_POST['regions'])) {
                $regionsData = json_decode($_POST['regions'], true);
                if (is_array($regionsData)) {
                    $existingMethod = $this->client->shipping->getMethod($methodId);
                    $existingRegions = [];
                    foreach ($existingMethod['regions'] ?? [] as $region) {
                        $existingRegions[$region['countryCode']] = $region;
                    }

                    $processedCountries = [];
                    
                    foreach ($regionsData as $region) {
                        if (!empty($region['countryCode'])) {
                            $countryCode = $region['countryCode'];
                            $processedCountries[] = $countryCode;
                            
                            $regionData = [
                                'countryName' => $region['countryName'] ?? ''
                            ];
                            
                            // Check if this is Classic mode (weightBrackets) or Modern mode (baseCost)
                            if (isset($region['weightBrackets']) && is_array($region['weightBrackets'])) {
                                // Classic mode: weight brackets
                                $regionData['weightBrackets'] = $region['weightBrackets'];
                            } elseif (isset($region['baseCost'])) {
                                // Modern mode: base cost + cost per kg
                                $regionData['baseCost'] = (int)$region['baseCost'];
                                $regionData['costPerKg'] = !empty($region['costPerKg']) ? (int)$region['costPerKg'] : 0;
                            } else {
                                // Skip invalid regions
                                continue;
                            }
                            
                            // Free shipping threshold (both modes)
                            if (!empty($region['freeShippingThreshold'])) {
                                $regionData['freeShippingThreshold'] = (int)$region['freeShippingThreshold'];
                            }

                            if (isset($existingRegions[$countryCode])) {
                                $this->client->shipping->updateRegion($methodId, $countryCode, $regionData);
                            } else {
                                $regionData['countryCode'] = $countryCode;
                                $this->client->shipping->addRegion($methodId, $regionData);
                            }
                        }
                    }

                    foreach ($existingRegions as $countryCode => $region) {
                        if (!in_array($countryCode, $processedCountries)) {
                            $this->client->shipping->deleteRegion($methodId, $countryCode);
                        }
                    }
                }
            }
            
            $this->redirect('/shipping', 'Shipping method updated successfully');
        } catch (\Exception $e) {
            $formData = $_POST;
            $formData['id'] = $methodId;
            
            $content = $this->view->render('shipping-form', [
                'method' => $formData,
                'isEdit' => true,
                'error' => $e->getMessage(),
                'adminPath' => $this->adminPath
            ]);

            error_log($e->getMessage());
            
            echo $this->view->renderLayout('admin-layout', $content, [
                'title' => 'Edit Shipping Method - Admin Panel',
                'user' => $_SESSION['customer'],
                'adminPath' => $this->adminPath
            ]);
        }
    }

    public function handleDelete(): void
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $methodId = $input['methodId'] ?? '';
            
            if (!$methodId) {
                $this->jsonResponse(['success' => false, 'message' => 'Shipping method ID is required'], 400);
            }

            $this->client->shipping->deleteMethod($methodId);
            $this->jsonResponse(['success' => true, 'message' => 'Shipping method deleted successfully']);
        } catch (\Exception $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
