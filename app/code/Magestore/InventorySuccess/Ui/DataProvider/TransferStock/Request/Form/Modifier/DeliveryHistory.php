<?php

/**
 * Copyright © 2016 Magestore. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magestore\InventorySuccess\Ui\DataProvider\TransferStock\Request\Form\Modifier;

use Magento\Ui\Component\Form;
use Magestore\InventorySuccess\Model\TransferStock;
use Magento\Ui\Component\DynamicRows;
use Magento\Ui\Component\Modal;
use Magestore\InventorySuccess\Api\Data\TransferStock\TransferPermission;

/**
 * Class Related
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class DeliveryHistory extends \Magestore\InventorySuccess\Ui\DataProvider\TransferStock\Request\Form\Modifier\RequestStock
{
    protected $_sortOrder = '2';

    protected $_dataLinks = 'delivery_products';
    protected $_groupContainer = 'delivery_history';


    protected $_groupLabel = 'Delivery History';


    protected $_fieldsetContent = 'Please add or import products to create Delivery';


    protected $_buttonTitle = 'Select Products';


    protected $_modalTitle = 'Add Products to create delivery';


    protected $_modalButtonTitle = 'Add Selected Products';

    protected $_modifierConfig = [
        'button_set' => 'product_stock_button_set',
        'modal' => 'add_delivery_modal',
        'listing' => 'os_transferstock_delivery_product_selection',
        'form' => 'transferstock_request_form',
        'columns_ids' => 'os_transferstock_delivery_product_selection_columns.ids',
        'history_listing' => 'transferstock_delivery_history'
    ];

    protected $_mapFields = [
        'id' => 'product_id',
        'sku' => 'product_sku',
        'name' => 'product_name',
        'qty_requested' => 'qty_requested',
        'qty_delivered' => 'qty_delivered',
        'qty_received' => 'qty_received',
        'available_qty' => 'available_qty'

    ];


    public function getVisible(){
        
        $transferstock_id = $this->request->getParam('id');
        if($transferstock_id){
            $transferStock = $this->_transferStockFactory->create()->load($transferstock_id);
            if($transferStock->getStatus() != TransferStock::STATUS_PENDING ){
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function modifyData(array $data)
    {
        if(!$this->getVisible())
        {
            return $data;
        }

        $transferstock_id = $this->request->getParam('id');
        if ($transferstock_id) {
            $transferStock = $this->_transferStockFactory->create()->load($transferstock_id);
            $warehouseId = $transferStock->getSourceWarehouseId();
            /** \Magestore\InventorySuccess\Model\ResourceModel\TransferStock\TransferStockProduct\CollectionFactory */
            $products = $this->collection->getTransferStockProduct($transferstock_id,$warehouseId);
            $data[$transferstock_id]['links'][$this->_dataLinks] = [];
            $delivery_products =   $this->_locatorFactory->create()->getSesionByKey("delivery_products");
            $this->_locatorFactory->create()->refreshSessionByKey("delivery_products");

            $data[$transferstock_id]['links'][$this->_dataLinks] = $delivery_products;

        }
        return $data;
    }

    

    /**
     * {@inheritdoc}
     */
    public function modifyMeta(array $meta)
    {
        if(!$this->getVisible())
        {
            return $meta;
        }
        return parent::modifyMeta($meta);
    }

    /**
     * Retrieve child meta configuration
     *
     * @return array
     */
    protected function getModifierChildren()
    {
        if($this->canShowButtons()){
            $children = [
                $this->_modifierConfig['button_set'] => $this->getCustomButtons(),
                $this->_modifierConfig['modal'] => $this->getCustomModal(),
                $this->_dataLinks => $this->getDynamicGrid(),
                $this->_modifierConfig['history_listing'] => $this->getDeliveryHistoryListing(),
            ];
        }
        else{
            $children = [
                $this->_modifierConfig['modal'] => $this->getCustomModal(),
                $this->_dataLinks => $this->getDynamicGrid(),
                $this->_modifierConfig['history_listing'] => $this->getDeliveryHistoryListing(),
            ];
        }

        return $children;
    }

    /**
     * hide all buttons: import, select product, save delivery when the current transfer is completed
     * @return bool
     */
    public function canShowButtons(){
        $transferstock_id = $this->request->getParam('id');
        if($transferstock_id){
            $transferStock = $this->_transferStockFactory->create()->load($transferstock_id);
            if($transferStock->getStatus() == TransferStock::STATUS_COMPLETED ){
                return false;
            }
        }

        if(!$this->_permissionManagement->checkPermission(TransferPermission::REQUEST_STOCK_ADD_DELIVERY)){
           return false;
        }

        return true;
    }

    /**
     * Returns dynamic rows configuration
     *
     * @return array
     */
    protected function getDynamicGrid()
    {
        $delivery_products =   $this->_locatorFactory->create()->getSesionByKey("delivery_products");

        $ShowColumnHeader = false;
        if(count($delivery_products)){
            $ShowColumnHeader = true;
        }
        $grid = [
            'arguments' => [
                'data' => [
                    'config' => [
                        'additionalClasses' => 'admin__field-wide',
                        'componentType' => DynamicRows::NAME,
                        'label' => null,
                        'renderDefaultRecord' => false,
                        'template' => 'ui/dynamic-rows/templates/grid',
                        'component' => 'Magento_Ui/js/dynamic-rows/dynamic-rows-grid',
                        'addButton' => false,
                        'itemTemplate' => 'record',
                        'dataScope' => 'data.links',
                        'deleteButtonLabel' => __('Remove'),
                        'dataProvider' => $this->_modifierConfig['listing'],
                        'map' => $this->_mapFields,
                        'links' => ['insertData' => '${ $.provider }:${ $.dataProvider }'],
                        'sortOrder' => 20,
                        'columnsHeader' => $ShowColumnHeader,
                        'columnsHeaderAfterRender' => true,
                    ],
                ],
            ],
            'children' => $this->getRows(),
        ];
        return $grid;
    }

    /**
     * Returns Buttons Set configuration
     *
     * @return array
     */
    protected function getCustomButtons()
    {
        return [
            'arguments' => [
                'data' => [
                    'config' => [
                        'formElement' => 'container',
                        'componentType' => 'container',
                        'label' => false,
                        'content' => __($this->_fieldsetContent),
                        'template' => 'ui/form/components/complex',
                    ],
                ],
            ],
            'children' => [
                'import_button' => [
                    'arguments' => [
                        'data' => [
                            'config' => [
                                'formElement' => 'container',
                                'componentType' => 'container',
                                'component' => 'Magestore_InventorySuccess/js/transferstock/import-delivery-button',
                                'actions' => [],
                                'title' => __('Import'),
                                'provider' => null,
                            ],
                        ],
                    ],
                ],
                'delivery_add_product_button' => [
                    'arguments' => [
                        'data' => [
                            'config' => [
                                'formElement' => 'container',
                                'componentType' => 'container',
                                'component' => 'Magento_Ui/js/form/components/button',
                                'actions' => [
                                    [
                                        'targetName' =>
                                            $this->_modifierConfig['form'] . '.' . $this->_modifierConfig['form']
                                            . '.'
                                            . $this->_groupContainer
                                            . '.'
                                            . $this->_modifierConfig['modal'],
                                        'actionName' => 'openModal',
                                    ],
                                    [
                                        'targetName' =>
                                            $this->_modifierConfig['form'] . '.' . $this->_modifierConfig['form']
                                            . '.'
                                            . $this->_groupContainer
                                            . '.'
                                            . $this->_modifierConfig['modal']
                                            . '.'
                                            . $this->_modifierConfig['listing'],
                                        'actionName' => 'render',
                                    ],
                                ],
                                'title' => __($this->_buttonTitle),
                                'provider' => null,
                            ],
                        ],
                    ],
                ],

                'delivery_save_button' => [
                    'arguments' => [
                        'data' => [
                            'config' => [
                                'formElement' => 'container',
                                'componentType' => 'container',
                                'component' => 'Magento_Ui/js/form/components/button',
                                'actions' => [
                                    [
                                        'targetName' =>
                                            $this->_modifierConfig['form'] . '.' . $this->_modifierConfig['form'],

                                        'actionName' => 'save',
                                        'params' => [
                                            true,
                                            [
                                                'id' => $this->request->getParam('id'),
                                                'action' => 'save_delivery'
                                            ],
                                        ]
                                    ]
                                ],
                                'title' => __('Save Delivery'),
                                'provider' => null,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }



    protected function getDeliveryHistoryListing(){
        $grid = [
            'arguments' => [
                'data' => [
                    'config' => [
                        'autoRender' => true,
                        'componentType' => 'insertListing',
                        'dataScope' => $this->_modifierConfig['history_listing'],
                        'ns' => $this->_modifierConfig['history_listing'],
                        'params' =>[
                            'id'=>$this->request->getParam('id')
                        ],
                        'render_url' => $this->urlBuilder->getUrl('mui/index/render'),
                        'realTimeLink' => true,
                        'behaviourType' => 'simple',
                        'externalFilterMode' => true,
                    ],
                ],
            ],
        ];
        return $grid;
    }

    /**
     * Fill meta columns
     *
     * @return array
     */
    protected function fillModifierMeta()
    {
        return [
            'id' => $this->getTextColumn('id', true, __('ID'), 10),
            'sku' => $this->getTextColumn('sku', false, __('SKU'), 20),
            'name' => $this->getTextColumn('name', false, __('Name'), 30),
            'qty_requested' => $this->getTextColumn('qty_requested', false, __('Qty Requested'), 40),
            'qty_delivered' => $this->getTextColumn('qty_delivered', false, __('Qty Delivered'), 50),
            'qty_received' => $this->getTextColumn('qty_received', false, __('Qty Received'), 60),
            'available_qty' => $this->getTextColumn('available_qty', false, __('Qty in Warehouse'), 61),
            'qty' => [
                'arguments' => [
                    'data' => [
                        'config' => [
                            'dataType' => Form\Element\DataType\Number::NAME,
                            'formElement' => Form\Element\Input::NAME,
                            'componentType' => Form\Field::NAME,
                            'dataScope' => 'qty',
                            'label' => __('Qty'),
                            'fit' => true,
                            'additionalClasses' => 'admin__field-small',
                            'sortOrder' => 70,
                            'required' => true,
                            'validation' => [
                                'validate-number' => true,
                                'validate-greater-than-zero' => true,
                                'required-entry' => true,
                            ],
                        ],
                    ],
                ],
            ],

            'actionDelete' => [
                'arguments' => [
                    'data' => [
                        'config' => [
                            'additionalClasses' => 'data-grid-actions-cell',
                            'componentType' => 'actionDelete',
                            'dataType' => Form\Element\DataType\Text::NAME,
                            'label' => __('Actions'),
                            'sortOrder' => 80,
                            'fit' => true,
                        ],
                    ],
                ],
            ],
            'position' => [
                'arguments' => [
                    'data' => [
                        'config' => [
                            'dataType' => Form\Element\DataType\Number::NAME,
                            'formElement' => Form\Element\Input::NAME,
                            'componentType' => Form\Field::NAME,
                            'dataScope' => 'position',
                            'sortOrder' => 90,
                            'visible' => false,
                        ],
                    ],
                ],
            ],
        ];
    }
}