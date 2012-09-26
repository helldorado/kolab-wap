<?php
/*
 +--------------------------------------------------------------------------+
 | This file is part of the Kolab Web Admin Panel                           |
 |                                                                          |
 | Copyright (C) 2011-2012, Kolab Systems AG                                |
 |                                                                          |
 | This program is free software: you can redistribute it and/or modify     |
 | it under the terms of the GNU Affero General Public License as published |
 | by the Free Software Foundation, either version 3 of the License, or     |
 | (at your option) any later version.                                      |
 |                                                                          |
 | This program is distributed in the hope that it will be useful,          |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of           |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the             |
 | GNU Affero General Public License for more details.                      |
 |                                                                          |
 | You should have received a copy of the GNU Affero General Public License |
 | along with this program. If not, see <http://www.gnu.org/licenses/>      |
 +--------------------------------------------------------------------------+
 | Author: Aleksander Machniak <machniak@kolabsys.com>                      |
 +--------------------------------------------------------------------------+
*/

/**
 * HTML output generation
 */
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
        'type', 'size', 'maxlength', 'value', 'autofocus');
    public static $select_attribs = array('multiple', 'name', 'size', 'disabled', 'readonly', 'autofocus');
    public static $option_attribs = array('selected', 'value', 'disabled', 'readonly');
    public static $a_attribs      = array('href', 'name', 'rel', 'tabindex', 'target');
    public static $form_attribs   = array('action', 'enctype', 'method', 'name', 'target');
    public static $label_attribs  = array('for');


    /**
     * Table element (TABLE).
     *
     * @param array  $attribs  Table attributes
     * @param string $content  Optional table content. If empty
     *                         head, body, foot attributes will be used.
     *
     * @return string HTML output of the table
     */
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

    /**
     * Table row (TR).
     *
     * @param array  $attribs  Row attributes
     * @param string $is_head  Set to true if it is a part of table head.
     *
     * @return string HTML output of the row
     */
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

    /**
     * Table cell (TD or TH).
     *
     * @param array  $attribs  Cell attributes
     * @param string $is_head  Set to true if it is a part of table head.
     *
     * @return string HTML output of the cell
     */
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

    /**
     * Input element.
     *
     * @param array  $attribs  Element attributes
     *
     * @return string HTML output of the input
     */
    public static function input($attribs = array())
    {
        $elem_attribs = array_merge(self::$input_attribs, self::$input_event_attribs,
            self::$common_attribs, self::$event_attribs);

        return sprintf('<input%s />', self::attrib_string($attribs, $elem_attribs));
    }

    /**
     * Textarea element.
     *
     * @param array $attribs  Element attributes
     * @param bool  $escape   Enables escaping of the content
     *
     * @return string HTML output of the textarea
     */
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

    /**
     * Select element.
     *
     * @param array $attribs  Element attributes
     * @param bool  $escape   Enables escaping of the content
     *
     * @return string HTML output of the select tag
     */
    public static function select($attribs = array(), $escape = false)
    {
        $elem_attribs = array_merge(self::$select_attribs, self::$input_event_attribs,
            self::$common_attribs, self::$event_attribs);

        $content = array();
        if (!empty($attribs['options']) && is_array($attribs['options'])) {
            foreach ($attribs['options'] as $idx => $option) {
                if (!is_array($option)) {
                    $option = array('content' => $option);
                }
                if (empty($option['value'])) {
                    $option['value'] = $idx;
                }
                if (!empty($attribs['value'])) {
                    if (is_array($attribs['value'])) {
                        $option['selected'] = in_array($option['value'], $attribs['value']);
                    }
                    else if ($attribs['value'] == $option['value']) {
                        $option['selected'] = true;
                    }
                }
                // make a select really readonly by disabling options
                else if (!empty($attribs['disabled']) || !empty($attribs['readonly'])) {
                    $option['disabled'] = true;
                }

                $content[] = self::option($option, $escape);
            }
        }

        return sprintf('<select%s>%s</select>',
            self::attrib_string($attribs, $elem_attribs), implode("\n", $content));
    }

    /**
     * Option element.
     *
     * @param array $attribs  Element attributes
     * @param bool  $escape   Enables escaping of the content
     *
     * @return string HTML output of the option tag
     */
    public static function option($attribs = array(), $escape = false)
    {
        $elem_attribs = array_merge(self::$option_attribs, self::$common_attribs);

        $content = isset($attribs['content']) ? $attribs['content'] : '';

        if ($escape) {
            $content = self::escape($content);
        }

        return sprintf('<option%s>%s</option>',
            self::attrib_string($attribs, $elem_attribs), $content);
    }

    /**
     * Fieldset element.
     *
     * @param array $attribs  Element attributes
     * @param bool  $escape   Enables escaping of the content
     *
     * @return string HTML output of the fieldset tag
     */
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

    /**
     * Link element (A).
     *
     * @param array $attribs  Element attributes
     * @param bool  $escape   Enables escaping of the content
     *
     * @return string HTML output of the link
     */
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

    /**
     * Label element.
     *
     * @param array $attribs  Element attributes
     * @param bool  $escape   Enables escaping of the content
     *
     * @return string HTML output of the label tag
     */
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

    /**
     * Division element.
     *
     * @param array $attribs  Element attributes
     * @param bool  $escape   Enables escaping of the content
     *
     * @return string HTML output of the div tag
     */
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

    /**
     * Span element.
     *
     * @param array $attribs  Element attributes
     * @param bool  $escape   Enables escaping of the content
     *
     * @return string HTML output of the span tag
     */
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

    /**
     * Form element.
     *
     * @param array  $attribs  Element attributes
     * @param string $escape   Content of the form
     *
     * @return string HTML output of the form tag
     */
    public static function form($attribs = array(), $content = null)
    {
        $elem_attribs = array_merge(self::$form_attribs, self::$common_attribs, self::$event_attribs);

        return sprintf('<form%s>%s</form>',
            self::attrib_string($attribs, $elem_attribs), $content);
    }

    /**
     * Script element.
     *
     * @param array $attribs  Element attributes
     * @param bool  $escape   Enables escaping of the content
     *
     * @return string HTML output of the script tag
     */
    public static function script($content = null, $escape = false)
    {
        if ($escape) {
            $content = self::escape($content);
        }

        return sprintf('<script type="text/javascript">%s</script>', $content);
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

            // ignore empty values
            if ($value === null) {
                continue;
            }

            // ignore unpermitted attributes, allow "data-"
            if (!empty($allowed) && strpos($key, 'data-') !== 0 && !isset($allowed[$key])) {
                continue;
            }

            // skip empty eventhandlers
            if (preg_match('/^on[a-z]+/', $key) && !$value) {
                continue;
            }

            // boolean attributes
            if (preg_match('/^(checked|multiple|disabled|selected|readonly|autofocus)$/', $key)) {
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

    /**
     * Escape special characters into HTML entities.
     *
     * @param string|array $value  Value to escape
     *
     * @return string|array Escaped value
     */
    public static function escape($value)
    {
        if (is_array($value)) {
            foreach ($value as $idx => $val) {
                $value[$idx] = self::escape($val);
            }
            return $value;
        }

        return htmlspecialchars($value, ENT_COMPAT, KADM_CHARSET);
    }
}
