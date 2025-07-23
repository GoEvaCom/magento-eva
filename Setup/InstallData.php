<?php
namespace GoEvaCom\Integration\Setup;

use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Eav\Setup\EavSetupFactory;
use GoEvaCom\Integration\Helper\AttributeManager;

class InstallData implements InstallDataInterface
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
    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
        $this->attributeManager->createAttribute($eavSetup);

        $setup->endSetup();
    }
}