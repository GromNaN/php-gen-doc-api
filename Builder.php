<?php

namespace Zckrs\GenDocApi;

use Zckrs\GenDocApi\Extractor;

class Builder
{
    /**
     * Version number
     *
     * @var string
     */
    const VERSION = '1.4';

    /**
     * Classes collection
     *
     * @var array
     */
    private $_st_classes;

    /**
     * Output directory for documentation
     *
     * @var string
     */
    private $_output_dir;

    /**
     * Output filename for documentation
     *
     * @var string
     */
    private $_output_file;

    /**
     * Constructor
     *
     * @param array $st_classes
     */
    public function __construct(array $st_classes, $s_output_dir, $s_output_file = 'index.html')
    {
        $this->_st_classes = $st_classes;
        $this->_output_dir = $s_output_dir;
        $this->_output_file = $s_output_file;
    }

    /**
     * Extract annotations
     *
     * @return array
     */
    private function extractAnnotations()
    {
        foreach ($this->_st_classes as $class) {
            $st_output[] = Extractor::getAllClassAnnotations($class);
        }

        return end($st_output);
    }

    private function saveTemplate($data, $anchorMenu, $file)
    {
        $template   = __DIR__.'/Resources/views/layout.html';
        $oldContent = file_get_contents($template);

        $css = file_get_contents(__DIR__.'/Resources/assets/css.css');
        $js  = file_get_contents(__DIR__.'/Resources/assets/js.js');

        $tr = array(
            '{{ content }}'     => $data,
            '{{ anchor_menu }}' => $anchorMenu,
            '{{ date }}'        => date('Y-m-d, H:i:s'),
            '{{ version }}'     => static::VERSION,
            '{{ css }}'         => $css,
            '{{ js }}'          => $js,
        );
        $newContent = strtr($oldContent, $tr);

        if (!is_dir($this->_output_dir)) {
            if (!mkdir($this->_output_dir)) {
                throw new \Exception('Cannot create directory');
            }
        }
        if (!file_put_contents($this->_output_dir.'/'.$file, $newContent)) {
            throw new \Exception('Cannot save the content to '.$this->_output_dir);
        }
    }

    /**
     * Generate the content of the documentation
     *
     * @return boolean
     */
    private function generateTemplate()
    {
        $st_annotations = $this->extractAnnotations();

        $template = array();
        $anchorMenu = array();
        $counter = 0;
        $section = null;

        $contentMainTpl  = file_get_contents(__DIR__.'/Resources/views/content/contentMain.html');
        $sectionTitleTpl = file_get_contents(__DIR__.'/Resources/views/content/sectionTitle.html');

        $anchorTpl = file_get_contents(__DIR__.'/Resources/views/menu/anchor.html');


        foreach ($st_annotations as $class => $methods) {
            foreach ($methods as $name => $docs) {
                if (isset($docs['ApiDescription'][0]['section']) && $docs['ApiDescription'][0]['section'] !== $section) {
                    $section = $docs['ApiDescription'][0]['section'];
                    $template[] = strtr($sectionTitleTpl, array(
                        '{{ elt_id }}'  => $counter,
                        '{{ section }}' => $section,
                    ));

                    $anchorMenu[] = strtr($anchorTpl, array(
                        '{{ elt_id }}'  => $counter,
                        '{{ section }}' => $section,
                    ));
                }
                if (0 === count($docs)) {
                    continue;
                }
                $tr = array(
                    '{{ elt_id }}'                => $counter,
                    '{{ method }}'                => $this->generateBadgeForMethod($docs),
                    '{{ route }}'                 => $docs['ApiRoute'][0]['name'],
                    '{{ description }}'           => $docs['ApiDescription'][0]['description'],
                    '{{ parameters }}'            => $this->generateParamsTemplate($counter, $docs),
                    '{{ table_object_response }}' => $this->generateResponseClasses($docs, $counter),
                    '{{ sample_root_object }}'    => $this->generateRootSample($docs),
                );
                $template[] = strtr($contentMainTpl, $tr);

                // Create a anchor for each ApiReturnObject['section']
                //$anchorMenu[] = $this->generateAnchorMenu($docs, $counter);

                $counter++;
            }
        }

        $this->saveTemplate(implode(PHP_EOL, $template), implode(PHP_EOL, $anchorMenu), $this->_output_file);

        return true;
    }

    /**
     * Generate the root sample JSON object
     *
     * @param  array   $st_params
     * @param  integer $counter
     * @return string
     */
    private function generateRootSample($st_params)
    {
        if (!isset($st_params['ApiReturnRootSample'][0])) {
            return '';
        }

        return '<h5>Return JSON root object :</h5><pre class="sample_root_object prettyprint">'.$st_params['ApiReturnRootSample'][0]['sample'].'</pre>';
    }

    /**
     * Generate anchor menu item
     *
     * @param  array   $st_params
     * @param  integer $counter
     * @return string
     */
    private function generateAnchorMenu($st_params, $counter)
    {
        if (!isset($st_params['ApiReturnObject'][0])) {
            return '';
        }

        $ret = array();
        $sections = array();

        foreach ($st_params['ApiReturnObject'] as $params) {

            if (!in_array($params['section'], $sections)) {
                $ret[] = strtr('&nbsp;&nbsp;&nbsp;<a href="#{{ section }}_anchor_{{ elt_id }}">{{ section }}</a><br/>',
                    array(
                        '{{ elt_id }}'  => $counter,
                        '{{ section }}' => $params['section'],
                    )
                );
                array_push($sections, $params['section']);
            }
        }

        return implode(PHP_EOL, $ret);
    }

    /**
     * Generate the object response
     *
     * @param  array   $st_params
     * @param  integer $counter
     * @return string
     */
    private function generateResponseClasses($st_params, $counter)
    {
        if (!isset($st_params['ApiReturnObject'])) {
            return 'NA';
        }

        $mainTpl    = file_get_contents(__DIR__.'/Resources/views/content/responseClasses/main.html');
        $tHeaderTpl = file_get_contents(__DIR__.'/Resources/views/content/responseClasses/tableHeaderSection.html');
        $bodyTpl    = file_get_contents(__DIR__.'/Resources/views/content/responseClasses/body.html');
        $linkTpl    = file_get_contents(__DIR__.'/Resources/views/content/responseClasses/link.html');

        $ret = array();
        $sections = array();

        foreach ($st_params['ApiReturnObject'] as $params) {

            $tr = array(
                '{{ elt_id }}' => $counter,
                '{{ name }}'   => $params['name'],
                '{{ type }}'   => $params['type'],
                '{{ note }}'   => @$params['note'],
                '{{ desc }}'   => $params['desc'],
            );

            if (isset($params['link'])) {
                $tr['{{ desc }}'] .= ' '.strtr($linkTpl, array(
                    '{{ elt_id }}' => $counter,
                    '{{ link }}'   => $params['link'],
                ));
            }

            if (!in_array($params['section'], $sections)) {
                $ret[] = strtr($tHeaderTpl, array(
                    '{{ elt_id }}'  => $counter,
                    '{{ section }}' => $params['section'],
                ));
                array_push($sections, $params['section']);
            }

            $ret[] = strtr($bodyTpl, $tr);
        }


        return strtr($mainTpl, array(
            '{{ elt_id }}' => $counter,
            '{{ responseTableBody }}' => implode(PHP_EOL, $ret),
        ));
    }

    /**
     * Generates the template for parameters
     *
     * @param  int         $id
     * @param  array       $st_params
     * @return void|string
     */
    private function generateParamsTemplate($counter, $st_params)
    {
        if (!isset($st_params['ApiParams']))
        {
            return;
        }

        $tableTpl   = file_get_contents(__DIR__.'/Resources/views/content/pathParameters/table.html');
        $tBodyTpl   = file_get_contents(__DIR__.'/Resources/views/content/pathParameters/tBody.html');
        $popoverTpl = file_get_contents(__DIR__.'/Resources/views/content/pathParameters/popover.html');

        $body = array();
        foreach ($st_params['ApiParams'] as $params) {
            $tr = array(
                '{{ name }}'        => $params['name'],
                '{{ type }}'        => $params['type'],
                '{{ nullable }}'    => @$params['nullable'] == '1' ? 'optional' : 'required',
                '{{ description }}' => @$params['description'],
            );
            if (isset($params['sample'])) {
                $tr['{{ type }}'].= ' '.strtr($popoverTpl, array('{{ sample }}' => $params['sample']));
            }
            $body[] = strtr($tBodyTpl, $tr);
        }

        return strtr($tableTpl, array(
            '{{ elt_id }}' => $counter,
            '{{ method }}' => $st_params['ApiMethod'][0]['type'],
            '{{ route }}'  => $st_params['ApiRoute'][0]['name'],
            '{{ tbody }}' => implode(PHP_EOL, $body),
        ));
    }

    /**
     * Generates a badge for method
     *
     * @param  array  $data
     * @return string
     */
    private function generateBadgeForMethod($data)
    {
        $method = strtoupper($data['ApiMethod'][0]['type']);
        $st_labels = array(
            'POST'   => 'label-primary',
            'GET'    => 'label-success',
            'PUT'    => 'label-warning',
            'DELETE' => 'label-danger'
        );

        return '<span class="label '.$st_labels[$method].'">'.$method.'</span>';
    }

    /**
     * Build the docs
     */
    public function generate()
    {
        return $this->generateTemplate();
    }

}
