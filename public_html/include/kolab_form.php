<?php


class kolab_form
{
    const INPUT_TEXT = 1;
    const INPUT_PASSWORD = 2;
    const INPUT_TEXTAREA = 3;
    const INPUT_CHECKBOX = 4;
    const INPUT_RADIO = 5;
    const INPUT_BUTTON = 6;
    const INPUT_SUBMIT = 7;

    private $attribs  = array();
    private $elements = array();
    private $sections = array();


    public function __construct($attribs = array())
    {
        $this->attribs = $attribs;
    }

    public function add_section($index, $legend)
    {
        $this->sections[$index] = $legend;
    }

    public function add_element($attribs, $section = null)
    {
        if (!empty($section)) {
            $attribs['section'] = $section;
        }

        $this->elements[] = $attribs;
    }

    public function output()
    {
        $content = '';

        if (!empty($this->sections)) {
            foreach ($this->sections as $set_idx => $set) {
                $rows = array();

                foreach ($this->elements as $element) {
                    if (empty($element['section']) || $element['section'] != $set_idx) {
                        continue;
                    }

                    $rows[] = $this->form_row($element);
                }

                if (!empty($rows)) {
                    $content .= "\n" . kolab_html::fieldset(array(
                        'legend' => $set,
                        'content' => kolab_html::table(array('body' => $rows, 'class' => 'form'))
                    ));
                }
            }
        }

        $rows = array();

        foreach ($this->elements as $element) {
            if (!empty($element['section'])) {
                continue;
            }

            $rows[] = $this->form_row($element);
        }

        if (!empty($rows)) {
             $content = kolab_html::table(array('body' => $rows, 'class' => 'form'));
        }

        return kolab_html::form($this->attribs, $content);
    }

    private function form_row($element)
    {
        $cells = array(
            0 => array(
                'class' => 'label',
                'body' => $element['label'],
            ),
            1 => array(
                'class' => 'value',
                'body' => $this->get_element($element),
            ),
        );

        return array('cells' => $cells);
    }

    private function get_element($attribs)
    {
        $type = isset($attribs['type']) ? $attribs['type'] : 0;

        switch ($type) {
        case self::INPUT_TEXT:
        case self::INPUT_PASSWORD:
            // INPUT type
            $attribs['type'] = $type == self::INPUT_PASSWORD ? 'password' : 'text';
            // INPUT size
            if (empty($attribs['size'])) {
                $attribs['size'] = 40;
                if (!empty($attribs['maxlength'])) {
                    $attribs['size'] = $attribs['maxlength'] > 10 ? 40 : 10;
                }
            }

            $content = kolab_html::input($attribs);
            break;

        case self::INPUT_TEXTAREA:
            $content = kolab_html::textarea($attribs);
            break;

        default:
            if (is_array($attribs)) {
                $content = isset($attribs['value']) ? $attribs['value'] : '';
            }
            else {
                $content = $attribs;
            }
        }

        return $content;
    }

}
