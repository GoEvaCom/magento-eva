<?php
namespace GoEvaCom\Integration\Setup;

use Magento\Framework\Setup\UninstallInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Eav\Setup\EavSetupFactory;
use GoEvaCom\Integration\Helper\AttributeManager;

class UninstallData implements UninstallInterface
{
    /**
     * @var EavSetupFactory
     */
    private $eavSetupFactory;

    /**
     * @var AttributeManager
     */
    private $attributeManager;

    /**
     * @param EavSetupFactory $eavSetupFactory
     * @param AttributeManager $attributeManager
     */
    public function __construct(
        EavSetupFactory $eavSetupFactory,
        AttributeManager $attributeManager
    ) {
        $this->eavSetupFactory = $eavSetupFactory;
        $this->attributeManager = $attributeManager;
    }

    /**
     * {@inheritdoc}
     */
    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
        $this->attributeManager->removeAttribute($eavSetup);

        $setup->endSetup();
    }
}