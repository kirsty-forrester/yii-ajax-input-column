<?php

/**
 * AjaxInputColumn class file.
 * Simple class for displaying CGridView columns as editable text input fields which automatically update via ajax
 *
 * @author Kirsty Forrester <kirstyaforrester@gmail.com>
 * @copyright Copyright &copy; Kirsty Forrester 2013
 * @license GPL version 3.0
 */

class AjaxInputColumn extends CDataColumn
{
    /**
     * The class of the data column model
     * @var string
     */
    public $modelClass;

    /**
     * The URL to which the input value is sent via POST
     * If given as a string, will be used directly
     * If given as an array, will be normalized
     * @var mixed
     */
    public $url;

    /**
     * Whether scripts have been registered yet or not
     * @var boolean
     */
    public $scriptRegistered = false;

    /**
     * Initializes the column and attaches ajax update event
     */
    public function init()
    {
        $this->attachAjaxUpdateEvent();

        return parent::init();
    }

    /**
     * Renders the data cell content
     * @param  integer $row the row number (zero-based)
     * @param  mixed $data the data associated with the row
     */
    protected function renderDataCellContent($row, $data)
    {
        $this->modelClass = get_class($data);

        if($this->value !== null)
            $value = $this->evaluateExpression($this->value, array('data'=> $data, 'row'=> $row));
        elseif($this->name !== null)
            $value = CHtml::value($data, $this->name);

        if($value === null){
            echo $this->grid->nullDisplay;
        }else{
            $fieldId = $this->modelClass . '_' . strtolower($this->name) . '_' . $data->id;

            if(!$this->scriptRegistered){

                $script = $this->registerScripts();
                Yii::app()->clientScript->registerScript(__CLASS__ . '-ajax-input-column-' . $fieldId, "
                    $('#" . $this->grid->id."')
                    .parent()
                    .on('ajaxUpdate.yiiGridView',
                    '#" . $this->grid->id."', function(){" . $script . "});
                ");
                $this->scriptRegistered = true; // so don't register again
            }

            echo CHtml::textField($fieldId, $value, array(
                'class' => 'ajax-input-column',
                'data-id' => $data->id,
                'data-name' => $this->name,
                'data-previous-value' => $value,
                'data-url' => CHtml::normalizeUrl($this->url),
            ));
        }
    }

    /**
     * Register scripts
     */
    protected function registerScripts()
    {
        $cs = Yii::app()->clientScript;
        $cs->registerCoreScript('jquery');
        $script = "
            $('#".$this->grid->id."').on('blur keydown', '.ajax-input-column', function(e){

                if(e.type == 'keydown' && (e.which != 13))
                    return true;

                var id = $(this).data('id');
                var name = $(this).data('name');
                var value = $(this).val();
                var previousValue = $(this).data('previous-value');
                var url = $(this).data('url');

                if(value != previousValue){
                    $.post(url,
                    {id: id, name: name, value: value, class: '".$this->modelClass."'})
                    .done(function(data) {
                        $.fn.yiiGridView.update('" . $this->grid->id . "');
                        $(this).data('previous-value', value);
                    });
                }
            });
        ";

        $cs->registerScript('AjaxInputColumn', $script, CClientScript::POS_END);

        return $script;
    }

    /**
     * Attach ajax update event since Yii doesn't allow custom js events
     * Credit to Vitalets: https://github.com/vitalets
     * Code from: https://github.com/yiisoft/yii/issues/1313 
     */
    protected function attachAjaxUpdateEvent()
    {
        $trigger = '$("#"+id).trigger("ajaxUpdate");';

        // Check if trigger already inserted by another column
        if(strpos($this->grid->afterAjaxUpdate, $trigger) !== false) return;

            // Inserting trigger
            if(strlen($this->grid->afterAjaxUpdate)) {
                $orig = $this->grid->afterAjaxUpdate;
                if(strpos($orig, 'js:')===0) $orig = substr($orig,3);
                $orig = "\n($orig).apply(this, arguments);";
            } else {
                $orig = '';
            }
            $this->grid->afterAjaxUpdate = "js: function(id, data) {
            $trigger $orig
        }";
    }


}