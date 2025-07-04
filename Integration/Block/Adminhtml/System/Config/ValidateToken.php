<?php
namespace GoEvaCom\Integration\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class ValidateToken extends Field
{
    protected $_template = 'GoEvaCom_Integration::system/config/validate_token.phtml';

    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }

    public function getButtonHtml()
    {
        $button = $this->getLayout()->createBlock(
            'Magento\Backend\Block\Widget\Button'
        )->setData([
            'id' => 'validate_token_button',
            'label' => __('Validate Token'),
            'class' => 'action-default',
            'onclick' => 'validateEvaToken()'
        ]);

        return $button->toHtml();
    }

    public function getValidationUrl()
    {
        return $this->getUrl('evadelivery/system_config/validatetoken');
    }
}
?>