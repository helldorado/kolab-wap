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
    const INPUT_SELECT = 8;
    const INPUT_HIDDEN = 9;

    private $attribs  = array();
    private $elements = array();
    private $sections = array();
    private $buttons  = array();
    private $title;


    /**
     * Class constructor.
     *
     * @param array $attribs  Form attributes
     */
    public function __construct($attribs = array())
    {
        $this->attribs = $attribs;
    }

    /**
     * Adds form section definition.
     *
     * @param string $index   Section internal index
     * @param string $legend  Section label (fieldset's legend)
     */
    public function add_section($index, $legend)
    {
        $this->sections[$index] = $legend;
    }

    /**
     * Adds form element definition.
     *
     * @param array  $attribs  Element attributes
     * @param string $section  Section index
     */
    public function add_element($attribs, $section = null)
    {
        if (!empty($section)) {
            $attribs['section'] = $section;
        }

        $this->elements[] = $attribs;
    }

    /**
     * Adds form button definition.
     *
     * @param array  $attribs   Button attributes
     */
    public function add_button($attribs)
    {
        $this->buttons[] = $attribs;
    }

    /**
     * Sets form title (header).
     *
     * @param string $content  Form header content
     */
    public function set_title($content)
    {
        $this->title = $content;
    }

    /**
     * Returns HTML output of the form.
     *
     * @return string HTML output
     */
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

        // Add form buttons
        if (!empty($this->buttons)) {
            $buttons = '';
            foreach ($this->buttons as $button) {
                $button['type'] = 'button';
                if (empty($button['value'])) {
                    $button['value'] = $button['label'];
                }
                $buttons .= kolab_html::input($button);
            }

            $content .= "\n" . kolab_html::div(array(
                'class'   => 'formbuttons',
                'content' => $buttons
            ));
        }

        // Build form
        $content = kolab_html::form($this->attribs, $content);

        // Add title element
        if ($this->title) {
            $content = kolab_html::span(array(
                'content' => $this->title,
                'class'   => 'formtitle',
            )) . "\n" . $content;
        }

        // Add event trigger, so UI can rebuild the form e.g. adding tabs
        $content .= kolab_html::script('kadm.trigger_event(\'form-loaded\', \'' . $this->attribs['id'] . '\')');

        return $content;
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

        $attrib = array('cells' => $cells);

        if ($element['required']) {
            $attrib['class'] = 'required';
        }

        return $attrib;
    }

    private function get_element($attribs)
    {
        $type = isset($attribs['type']) ? $attribs['type'] : 0;

        if (!empty($attribs['readonly']) || !empty($attribs['disabled'])) {
            $attribs['class'] = (!empty($attribs['class']) ? $attribs['class'] . ' ' : '') . 'readonly';
        }

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

        case self::INPUT_HIDDEN:
            $attribs['type'] = 'hidden';
            $content = kolab_html::input($attribs);
            break;

        case self::INPUT_TEXTAREA:
            if (empty($attribs['rows'])) {
                $attribs['rows'] = 5;
            }
            if (empty($attribs['cols'])) {
                $attribs['cols'] = 50;
            }

            $content = kolab_html::textarea($attribs, true);
            break;

        case self::INPUT_SELECT:
            $content = kolab_html::select($attribs);
            break;

        default:
            if (is_array($attribs)) {
                $content = isset($attribs['value']) ? $attribs['value'] : '';
            }
            else {
                $content = $attribs;
            }
        }

        if (!empty($attribs['suffix'])) {
            $content .= ' ' . $attribs['suffix'];
        }

        return $content;
    }

}
