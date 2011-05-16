<?php
namespace webapp;

// XXX: in development

require_once dirname(__FILE__) . '/Annotation.php';

class Form
{
	public $action;
	public $method = 'POST';

	private $klass;
	private $items;
	private $values;

	function __construct($klass=null)
	{
		if ($klass)
			$this->setClass($klass);
	}

	public function setClass($klass)
	{
		$this->klass = new \ReflectionAnnotatedClass($klass);
		$this->parse();
	}

	public static function fromClass($klass)
	{
		$form = new Form();
		$form->setClass($klass);
		return $form;
	}

	private function parse()
	{
		if (!$this->klass)
			throw new \Exception("class not set");

		if ($this->klass->hasAnnotation('Form')) {
			$form = $this->klass->getAnnotation('Form');
			if ($form->action)
				$this->action = $form->action;
			if ($form->method)
				$this->method = $form->method;
		}

		$this->items = array();
		foreach ($this->klass->getProperties() as $prop) {
			if (substr($prop->getName(), 0, 1) == '_')
				continue;
			$item = new FormItem();
			$item->name = $prop->getName();
			if ($prop->hasAnnotation('FormItem')) {
				$annotate = $prop->getAnnotation('FormItem');
				if ('' != $annotate->label)
					$item->label = $annotate->label;
				else
					$item->label = $prop->getName();

				if ($annotate->options)
					$item->options = $annotate->options;

				if ($annotate->layout)
					$this->layout = $annotate->layout;

				if ($annotate->type) {
					$item->type = $annotate->type;
				} else if ($prop->hasAnnotation('Column')) {
					$column = $prop->getAnnotation('Column');
					if (isset($column->type)) {
						if ($column->type == 'text')
							$item->type = 'textarea';
						else
							$item->type = $column->type;
					} else {
						$item->type = 'text';
					}
					if ($column->length)
						$item->length = $column->length;
				}
				if ($annotate->length) {
					$item->length = $annotate->length;
				}
			}
			if ($prop->hasAnnotation('Id'))
				$item->type = 'hidden';
			$this->items[] = $item;
		}
	}

	public function html($html5=false)
	{
		$form = simplexml_load_string('<form />');
		$form['action'] = $this->action;
		$form['method'] = $this->method;

		foreach ($this->items as $item) {
			$item->appendTo($form);
		}
		$submit = $form->addChild('input');
		$submit['type'] = 'submit';
		$lines = split("\n",$form->asXML());
		array_shift($lines);
		return implode('', $lines);
	}

	public function html5()
	{
		return $this->html(true);
	}
}

class FormItem
{
	public $type = 'string';
	public $label = '';
	public $name;
	public $length;
	public $options;
	public $layout;
	public $html5;

	function __construct($html5=false)
	{
		$this->html5 = $html5;
	}

	public function appendTo($xml)
	{
		$el = $xml->addChild('div');
		$el['class'] = ($this->type == 'hidden')
			? 'hiddenItem'
			: 'formItem';
		$label = $el->addChild('label', $this->label);
		$label['for'] = $this->name;
		$label['class'] = 'formLabel';

		$html5types = array('number', 'range', 'email', 'date');
		if (!$this->html5 && in_array($this->type, $html5types)) {
			$this->type = 'text';
		}
		switch ($this->type) {
		case 'string': case 'hidden': case 'password':
		case 'text': case 'number': case 'range': case 'email': case 'date':
			$input = $el->addChild('input');
			$input['type'] = $this->type;
			$input['name'] = $input['id'] = $this->name;
			break;
		case 'textarea':
			$input = $el->addChild('textarea', '');
			$input['name'] = $input['id'] = $this->name;
		case 'radio': case 'checkbox':
			if (is_array($this->options)) {
				$i = 0;
				foreach ($this->options as $option) {
					if (!is_array($option))
						$option = array('text'=>$option, 'value'=>$option);
					$input = $el->addChild('input');
					$input['type'] = $this->type;
					$input['name'] = ($this->type == 'radio')
						? $this->name
						: $this->name . '[]';
					$input['id'] = $this->name . '_' . ++$i;
					$input['value'] = $option['value'];

					$sublabel = $el->addChild('label', $option['text']);
					$sublabel['for'] = $input['id'];
					if ($this->layout == 'vertical') {
						$el->addChild('br');
					} else {
						$glue = $el->addChild('span', '  ');
						$glue['class'] = 'spacer';
					}
				}
			}
			break;
		case 'select': case 'select-multi':
			$input = $el->addChild('select');
			break;
		}
		return $el;
	}

	public function html5()
	{
		return $this->html(true);
	}
}

?>
