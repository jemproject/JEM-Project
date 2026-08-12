<?php
/** @package JEM */
defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;

class jem_tax_rates extends Table
{
    public $id = null;
    public $code = '';
    public $name = '';
    public $tax_type = 'standard';
    public $rate = '0.00';
    public $country_code = '';
    public $region_code = '';
    public $description = null;
    public $valid_from = null;
    public $valid_until = null;
    public $published = 1;
    public $ordering = 0;
    public $checked_out = null;
    public $checked_out_time = null;
    public $created = null;
    public $created_by = 0;
    public $modified = null;
    public $modified_by = 0;

    public function __construct(&$db)
    {
        parent::__construct('#__jem_tax_rates', 'id', $db);
    }

    public function check()
    {
        $this->code = strtoupper(trim((string) $this->code));
        $this->name = trim((string) $this->name);
        $this->tax_type = strtolower(trim((string) $this->tax_type));
        $this->country_code = strtoupper(trim((string) $this->country_code));
        $this->region_code = trim((string) $this->region_code);

        if (preg_match('/^[A-Z0-9][A-Z0-9_-]{0,63}$/D', $this->code) !== 1) {
            $this->setError(Text::_('COM_JEM_TAX_RATE_ERROR_CODE'));
            return false;
        }
        if ($this->name === '') {
            $this->setError(Text::_('COM_JEM_TAX_RATE_ERROR_NAME'));
            return false;
        }
        if (!in_array($this->tax_type, array('standard', 'reduced', 'zero', 'exempt', 'outside_scope'), true)) {
            $this->setError(Text::_('COM_JEM_TAX_RATE_ERROR_TYPE'));
            return false;
        }

        $rate = trim((string) $this->rate);
        if (preg_match('/^(\d{1,3})(?:\.(\d{1,2}))?$/D', $rate, $matches) !== 1) {
            $this->setError(Text::_('COM_JEM_TAX_RATE_ERROR_RATE'));
            return false;
        }
        $basisPoints = ((int) $matches[1] * 100) + (int) str_pad($matches[2] ?? '', 2, '0');
        if ($basisPoints > 10000 || (in_array($this->tax_type, array('zero', 'exempt', 'outside_scope'), true) && $basisPoints !== 0)) {
            $this->setError(Text::_('COM_JEM_TAX_RATE_ERROR_RATE'));
            return false;
        }
        $this->rate = number_format($basisPoints / 100, 2, '.', '');

        if ($this->country_code !== '' && preg_match('/^[A-Z]{2}$/D', $this->country_code) !== 1) {
            $this->setError(Text::_('COM_JEM_TAX_RATE_ERROR_COUNTRY'));
            return false;
        }
        foreach (array('valid_from', 'valid_until') as $field) {
            $value = trim((string) $this->{$field});
            $this->{$field} = $value === '' ? null : $value;
            if ($this->{$field} !== null && !$this->isValidDate($this->{$field})) {
                $this->setError(Text::_('COM_JEM_TAX_RATE_ERROR_DATES'));
                return false;
            }
        }
        if ($this->valid_from !== null && $this->valid_until !== null && $this->valid_until < $this->valid_from) {
            $this->setError(Text::_('COM_JEM_TAX_RATE_ERROR_DATES'));
            return false;
        }

        $this->published = (int) $this->published === 1 ? 1 : 0;
        $this->ordering = (int) $this->ordering;

        return parent::check();
    }

    private function isValidDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();

        return $date !== false
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            && $date->format('Y-m-d') === $value;
    }
}
