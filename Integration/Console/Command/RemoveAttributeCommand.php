<?php
namespace GoEvaCom\Integration\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use GoEvaCom\Integration\Helper\AttributeManager;

class RemoveAttributeCommand extends Command
{
    /**
     * @var EavSetupFactory
     */
    private $eavSetupFactory;

    /**
     * @var ModuleDataSetupInterface
     */
    private $setup;

    /**
     * @var AttributeManager
     */
    private $attributeManager;

    /**
     * @param EavSetupFactory $eavSetupFactory
     * @param ModuleDataSetupInterface $setup
     * @param AttributeManager $attributeManager
     */
    public function __construct(
        EavSetupFactory $eavSetupFactory,
        ModuleDataSetupInterface $setup,
        AttributeManager $attributeManager
    ) {
        $this->eavSetupFactory = $eavSetupFactory;
        $this->setup = $setup;
        $this->attributeManager = $attributeManager;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('evadelivery:attribute:remove')
            ->setDescription('Remove is_eva_deliverable attribute');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $eavSetup = $this->eavSetupFactory->create(['setup' => $this->setup]);
            $this->attributeManager->removeAttribute($eavSetup);
            
            $output->writeln('<info>Eva attribute removed successfully.</info>');
            return 0;
        } catch (\Exception $e) {
            $output->writeln('<error>Error removing attribute: ' . $e->getMessage() . '</error>');
            return 1;
        }
    }
}