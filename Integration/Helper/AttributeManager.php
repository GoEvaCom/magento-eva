<?php
namespace GoEvaCom\Integration\Helper;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetup;

class AttributeManager
{
    /**
     * Get attribute configuration
     *
     * @return array
     */
    public function getAttributeConfig()
    {
        return [
            'attribute_code' => 'is_eva_deliverable',
            'type' => 'int',
            'backend' => '',
            'frontend' => '',
            'label' => 'Livraison Express',
            'input' => 'boolean',
            'class' => '',
            'source' => 'Magento\Eav\Model\Entity\Attribute\Source\Boolean',
            'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
            'visible' => true,
            'required' => true,
            'user_defined' => true,
            'default' => '0',
            'searchable' => true,
            'filterable' => true,
            'comparable' => false,
            'visible_on_front' => false,
            'used_in_product_listing' => true,
            'unique' => false,
            'apply_to' => '',
            'system' => true,
            'group' => 'General',
            'sort_order' => 1000,
            'note' => 'Enable to make the product deliverable with Eva'
        ];
    }

    /**
     * Create the attribute
     *
     * @param EavSetup $eavSetup
     * @return void
     */
    public function createAttribute(EavSetup $eavSetup)
    {
        $config = $this->getAttributeConfig();
        
        $eavSetup->addAttribute(
            Product::ENTITY,
            $config['attribute_code'],
            [
                'type' => $config['type'],
                'backend' => $config['backend'],
                'frontend' => $config['frontend'],
                'label' => $config['label'],
                'input' => $config['input'],
                'class' => $config['class'],
                'source' => $config['source'],
                'global' => $config['global'],
                'visible' => $config['visible'],
                'required' => $config['required'],
                'user_defined' => $config['user_defined'],
                'default' => $config['default'],
                'searchable' => $config['searchable'],
                'filterable' => $config['filterable'],
                'comparable' => $config['comparable'],
                'visible_on_front' => $config['visible_on_front'],
                'used_in_product_listing' => $config['used_in_product_listing'],
                'unique' => $config['unique'],
                'apply_to' => $config['apply_to'],
                'system' => $config['system'],
                'note' => $config['note']
            ]
        );

        // Add attribute to attribute set
        $this->addAttributeToSet($eavSetup, $config);
    }

    /**
     * Remove the attribute
     *
     * @param EavSetup $eavSetup
     * @return void
     */
    public function removeAttribute(EavSetup $eavSetup)
    {
        $config = $this->getAttributeConfig();
        $eavSetup->removeAttribute(Product::ENTITY, $config['attribute_code']);
    }

    /**
     * Add attribute to default attribute set
     *
     * @param EavSetup $eavSetup
     * @param array $config
     * @return void
     */
    private function addAttributeToSet(EavSetup $eavSetup, array $config)
    {
        $entityTypeId = $eavSetup->getEntityTypeId(Product::ENTITY);
        $attributeSetId = $eavSetup->getDefaultAttributeSetId($entityTypeId);
        $attributeGroupId = $eavSetup->getAttributeGroupId($entityTypeId, $attributeSetId, $config['group']);

        $eavSetup->addAttributeToSet(
            $entityTypeId,
            $attributeSetId,
            $attributeGroupId,
            $config['attribute_code'],
            $config['sort_order']
        );
    }

    /**
     * Get attribute code
     *
     * @return string
     */
    public function getAttributeCode()
    {
        return $this->getAttributeConfig()['attribute_code'];
    }
}