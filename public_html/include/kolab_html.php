<?php


class kolab_html
{
    public static $common_attribs = array('id', 'class', 'style', 'title', 'align', 'dir');
    public static $event_attribs  = array('onclick', 'ondblclick', 'onmousedown', 'onmouseup',
        'onmouseover', 'onmousemove', 'onmouseout');
    public static $input_event_attribs = array('onfocus', 'onblur', 'onkeypress', 'onkeydown', 'onkeyup',
        'onsubmit', 'onreset', 'onselect', 'onchange');
    public static $table_attribs  = array('summary');
    public static $tr_attribs     = array();
    public static $td_attribs     = array('colspan', 'rowspan');
    public static $textarea_attribs = array('cols', 'rows', 'disabled', 'name', 'readonly', 'tabindex');
    public static $input_attribs  = array('checked', 'disabled', 'name', 'readonly', 'tabindex',
        'type', 'size', 'maxlength', 'value');
    public static $select_attribs = array('multiple', 'name', 'size', 'disabled');
    public static $option_attribs = array('selected', 'value', 'disabled');
    public static $a_attribs      = array('href', 'name', 'rel', 'tabindex', 'target');
    public static $form_attribs   = array('action', 'enctype', 'method', 'name', 'target');
    public static $label_attribs  = array('for');


    public static function table($attribs = array(), $content = null)
    {
        $table_attribs = array_merge(self::$table_attribs, self::$common_attribs, self::$event_attribs);
        $table = '<table' . self::attrib_string($attribs, $table_attribs) . '>';

        if ($content) {
            $table .= $content;
        }
        else {
            if (!empty($attribs['head']) && is_array($attribs['head'])) {
                $table .= '<thead>';
                foreach ($attribs['head'] as $row) {
                    $table .= "\n" . self::tr($row, null, true);
                }
                $table .= '</thead>';
            }
            if (!empty($attribs['body']) && is_array($attribs['body'])) {
                $table .= '<tbody>';
                foreach ($attribs['body'] as $row) {
                    $table .= "\n" . self::tr($row);
                }
                $table .= '</tbody>';
            }
            if (!empty($attribs['foot']) && is_array($attribs['foot'])) {
                $table .= '<tfoot>';
                foreach ($attribs['foot'] as $row) {
                    $table .= "\n" . self::tr($row);
                }
                $table .= '</tfoot>';
            }
        }

        $table .= "\n</table>";

        return $table;
    }

    public static function tr($attribs = array(), $is_head = false)
    {
        $row_attribs = array_merge(self::$tr_attribs, self::$common_attribs, self::$event_attribs);
        $row = '<tr' . self::attrib_string($attribs, $row_attribs) . '>';

        if (!empty($attribs['cells']) && is_array($attribs['cells'])) {
            foreach ($attribs['cells'] as $cell) {
                $row .= "\n" . self::td($cell, $is_head);
            }
        }

        $row .= "\n</tr>";

        return $row;
    }

    public static function td($attribs = array(), $is_head = false)
    {
        $cell_attribs = array_merge(self::$td_attribs, self::$common_attribs, self::$event_attribs);
        $tag = $is_head ? 'th' : 'td';
        $cell .= '<' . $tag . self::attrib_string($attribs, $cell_attribs) . '>';

        if (isset($attribs['body'])) {
            $cell .= $attribs['body'];
        }

        $cell .= "</$tag>";

        return $cell;
    }

    public static function input($attribs = array())
    {
        $elem_attribs = array_merge(self::$input_attribs, self::$input_event_attribs,
            self::$common_attribs, self::$event_attribs);

        return sprintf('<input%s />', self::attrib_string($attribs, $elem_attribs));
    }

    public static function textarea($attribs = array(), $escape = false)
    {
        $elem_attribs = array_merge(self::$textarea_attribs, self::$input_event_attribs,
            self::$common_attribs, self::$event_attribs);

        $content = isset($attribs['value']) ? $attribs['value'] : '';

        if ($escape) {
            $content = self::escape($content);
        }

        return sprintf('<textarea%s>%s</textarea>',
            self::attrib_string($attribs, $elem_attribs), $content);
    }

    public static function select($attribs = array(), $escape = false)
    {
        $elem_attribs = array_merge(self::$select_attribs, self::$input_event_attribs,
            self::$common_attribs, self::$event_attribs);

        $content = array();
        if (!empty($attribs['options']) && is_array($attribs['options'])) {
            foreach ($attribs['options'] as $option) {
                $content[] = self::option($option, $escape);
            }
        }

        return sprintf('<select%s>%s</select>',
            self::attrib_string($attribs, $elem_attribs), implode("\n", $content));
    }

    public static function option($attribs = array(), $escape = false)
    {
        $elem_attribs = array_merge(self::$option_attribs, self::$common_attribs);

        $content = isset($attribs['content']) ? $attribs['content'] : '';

        if ($escape) {
            $content = self::escape($content);
        }

        return sprintf('<textarea%s>%s</textarea>',
            self::attrib_string($attribs, $elem_attribs), $content);
    }

    public static function fieldset($attribs = array(), $escape = false)
    {
        $elem_attribs = array_merge(self::$common_attribs);

        $legend  = isset($attribs['legend']) ? $attribs['legend'] : $attribs['label'];
        $content = isset($attribs['content']) ? $attribs['content'] : '';

        if ($escape) {
            $legend = self::escape($legend);
        }

        return sprintf('<fieldset%s><legend>%s</legend>%s</fieldset>',
            self::attrib_string($attribs, $elem_attribs), $legend, $content);
    }

    public static function a($attribs = array(), $escape = false)
    {
        $elem_attribs = array_merge(self::$a_attribs, self::$common_attribs, self::$event_attribs);

        $content = isset($attribs['content']) ? $attribs['content'] : '';

        if ($escape) {
            $content = self::escape($content);
        }

        return sprintf('<a%s>%s</a>',
            self::attrib_string($attribs, $elem_attribs), $content);
    }

    public static function label($attribs = array(), $escape = false)
    {
        $elem_attribs = array_merge(self::$label_attribs, self::$common_attribs);

        $content = isset($attribs['content']) ? $attribs['content'] : '';

        if ($escape) {
            $content = self::escape($content);
        }

        return sprintf('<label%s>%s</label>',
            self::attrib_string($attribs, $elem_attribs), $content);
    }

    public static function div($attribs = array(), $escape = false)
    {
        $elem_attribs = array_merge(self::$common_attribs, self::$event_attribs);

        $content = isset($attribs['content']) ? $attribs['content'] : '';

        if ($escape) {
            $content = self::escape($content);
        }

        return sprintf('<div%s>%s</div>',
            self::attrib_string($attribs, $elem_attribs), $content);
    }

    public static function span($attribs = array(), $escape = false)
    {
        $elem_attribs = array_merge(self::$common_attribs, self::$event_attribs);

        $content = isset($attribs['content']) ? $attribs['content'] : '';

        if ($escape) {
            $content = self::escape($content);
        }

        return sprintf('<span%s>%s</span>',
            self::attrib_string($attribs, $elem_attribs), $content);
    }

    public static function form($attribs = array(), $content = null)
    {
        $elem_attribs = array_merge(self::$form_attribs, self::$common_attribs, self::$event_attribs);

        return sprintf('<form%s>%s</form>',
            self::attrib_string($attribs, $elem_attribs), $content);
    }

    /**
     * Create string with attributes
     *
     * @param array $attrib   Associative array with tag attributes
     * @param array $allowed  List of allowed attributes
     *
     * @return string Valid attribute string
     */
    public static function attrib_string($attrib = array(), $allowed = array())
    {
        if (empty($attrib)) {
            return '';
        }

        $allowed    = array_flip((array)$allowed);
        $attrib_arr = array();

        foreach ($attrib as $key => $value) {
            // skip size if not numeric
            if (($key == 'size' && !is_numeric($value))) {
                continue;
            }

            // ignore "internal" or not allowed attributes
            if ((!empty($allowed) && !isset($allowed[$key])) || $value === null) {
                continue;
            }

            // skip empty eventhandlers
            if (preg_match('/^on[a-z]+/', $key) && !$value) {
                continue;
            }

            // boolean attributes
            if (preg_match('/^(checked|multiple|disabled|selected|readonly)$/', $key)) {
                if ($value) {
                    $attrib_arr[] = sprintf('%s="%s"', $key, $key);
                }
            }
            // the rest
            else {
                $attrib_arr[] = sprintf('%s="%s"', $key, self::escape($value));
            }
        }

        return count($attrib_arr) ? ' '.implode(' ', $attrib_arr) : '';
    }

    public static function escape($value)
    {
        return htmlspecialchars($value, null, KADM_CHARSET);
    }
}
