<?php
/**
 * This file is part of the php-apidoc package.
 */
namespace Crada\Apidoc;

use Crada\Apidoc\Extractor,
    Crada\Apidoc\View,
    Crada\Apidoc\View\JsonView,
    Crada\Apidoc\Exception;

/**
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 * @author  Calin Rada <rada.calin@gmail.com>
 */
class Builder
{
    /**
     * Version number
     *
     * @var string
     */
    const VERSION = '1.3.4';

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

    private function saveTemplate($data, $file)
    {
        $template   = __DIR__.'/Resources/views/template/index.html';
        $oldContent = file_get_contents($template);

        $tr = array(
            '{{ content }}' => $data,
            '{{ date }}'    => date('Y-m-d, H:i:s'),
            '{{ version }}' => static::VERSION,
        );
        $newContent = strtr($oldContent, $tr);

        if (!is_dir($this->_output_dir)) {
            if (!mkdir($this->_output_dir)) {
                throw new Exception('Cannot create directory');
            }
        }
        if (!file_put_contents($this->_output_dir.'/'.$file, $newContent)) {
            throw new Exception('Cannot save the content to '.$this->_output_dir);
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
        $counter = 0;
        $section = null;

        foreach ($st_annotations as $class => $methods) {
            foreach ($methods as $name => $docs) {
                if (isset($docs['ApiDescription'][0]['section']) && $docs['ApiDescription'][0]['section'] !== $section) {
                    $section = $docs['ApiDescription'][0]['section'];
                    $template[] = '<h2>'.$section.'</h2>';
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
                    '{{ sample_response }}'       => $this->generateSampleOutput($docs, $counter),
                    '{{ table_object_response }}' => $this->generateObjectResponse($docs, $counter),
                );
                $template[] = strtr(static::$mainTpl, $tr);
                $counter++;
            }
        }
        $this->saveTemplate(implode(PHP_EOL, $template), $this->_output_file);

        return true;
    }

    /**
     * Generate the sample output
     *
     * @param  array   $st_params
     * @param  integer $counter
     * @return string
     */
    private function generateObjectResponse($st_params, $counter)
    {
        if (!isset($st_params['ApiReturnObject'])) {
            return 'NA';
        }

        $ret = array();
        $sections = array();

        foreach ($st_params['ApiReturnObject'] as $params) {

            $tr = array(
                '{{ elt_id }}' => $counter,
                '{{ name }}'   => $params['name'],
                '{{ type }}'   => $params['type'],
                '{{ desc }}'   => $params['desc'],
            );

            if (!in_array($params['section'], $sections)) {
                $ret[] = strtr(static::$responseSectionTpl, array( '{{ section }}' => $params['section']));
                array_push($sections, $params['section']);
            }

            $ret[] = strtr(static::$reponseBodyTpl, $tr);
        }


        return strtr(static::$responseTpl, array(
            '{{ elt_id }}' => $counter,
            '{{ section }}' => $st_params['ApiReturnObject'][0]['section'],
            '{{ responseTableBody }}' => implode(PHP_EOL, $ret),
        ));
    }

        static $responseSectionTpl = '
<tr class="info"><th colspan="3"><i>{{ section }}</i></th></tr>';

        static $responseTpl = '
<table class="table table-hover">
    <thead>
        <tr>
            <th>Name</th>
            <th>Data Type</th>
            <th>Description</th>
        </tr>
    </thead>
    <tbody>
        {{ responseTableBody }}
    </tbody>
</table>';

        static $reponseBodyTpl = '
<tr>
    <td>{{ name }}</td>
    <td>{{ type }}</td>
    <td>{{ desc }}</td>
</tr>';

    /**
     * Generate the sample output
     *
     * @param  array   $st_params
     * @param  integer $counter
     * @return string
     */
    private function generateSampleOutput($st_params, $counter)
    {
        if (!isset($st_params['ApiSampleResponse'])) {
            return 'NA';
        }
        $ret = array();
        foreach ($st_params['ApiSampleResponse'] as $params) {
            if (in_array($params['type'], array('object', 'array(object) ', 'array')) && isset($params['sample'])) {
                $tr = array(
                    '{{ elt_id }}'      => $counter,
                    '{{ response }}'    => $params['sample'],
                    '{{ description }}' => '',
                );
                if (isset($params['description'])) {
                    $tr['{{ description }}'] = $params['description'];
                }
                $ret[] = strtr(static::$sampleReponseTpl, $tr);
            }
        }

        return implode(PHP_EOL, $ret);
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

        $body = array();
        foreach ($st_params['ApiParams'] as $params) {
            $tr = array(
                '{{ name }}'        => $params['name'],
                '{{ type }}'        => $params['type'],
                '{{ nullable }}'    => @$params['nullable'] == '1' ? 'optional' : 'required',
                '{{ description }}' => @$params['description'],
            );
            if (in_array($params['type'], array('object', 'array(object) ', 'array')) && isset($params['sample'])) {
                $tr['{{ type }}'].= ' '.strtr(static::$paramSampleBtnTpl, array('{{ sample }}' => $params['sample']));
            }
            $body[] = strtr(static::$paramContentTpl, $tr);
        }

        return strtr(static::$paramTableTpl, array(
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
     * Output the annotations in json format
     *
     * @return json
     */
    public function renderJson()
    {
        $st_annotations = $this->extractAnnotations();

        $o_view = new JsonView();
        $o_view->set('annotations', $st_annotations);
        $o_view->render();
    }

    /**
     * Output the annotations in json format
     *
     * @return array
     */
    public function renderArray()
    {
        return $this->extractAnnotations();
    }

    /**
     * Build the docs
     */
    public function generate()
    {
        return $this->generateTemplate();
    }

    public static $mainTpl = '
<div class="panel panel-default">
    <div class="panel-heading">
        <h4 class="panel-title">
            {{ method }} <a data-toggle="collapse" data-parent="#accordion{{ elt_id }}" href="#collapseOne{{ elt_id }}"> {{ route }}</a>
        </h4>
    </div>
    <div id="collapseOne{{ elt_id }}" class="panel-collapse collapse">
        <div class="panel-body">

            <!-- Nav tabs -->
            <!-- <ul class="nav nav-tabs" id="php-apidoctab{{ elt_id }}">
                <li class="active"><a href="#info{{ elt_id }}" data-toggle="tab">Info</a></li>
                <li><a href="#sandbox{{ elt_id }}" data-toggle="tab">Sandbox</a></li>
                <li><a href="#sample{{ elt_id }}" data-toggle="tab">Sample output</a></li>
            </ul> -->

            <!-- Tab panes -->
            <div class="tab-content">

                <div class="tab-pane active" id="info{{ elt_id }}">
                    <h4>Implementation Notes</h4>
                    {{ description }}<br/><br/>
                    <hr>

                    <h4>Response Classes</h4>
                    <pre class="prettyprint">
{
    "meta": metaObject,
    "response": responseObject
}</pre>

                    {{ table_object_response }}
                    <br/>
                    <hr>


                    <h4>Reponse Errors</h4>
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>HTTP Status Code</th>
                                <th>Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>400</td>
                                <td>Bad request</td>
                            </tr>
                            <tr>
                                <td>401</td>
                                <td>Unauthorized</td>
                            </tr>
                            <tr>
                                <td>404</td>
                                <td>Not Found</td>
                            </tr>
                            <tr>
                                <td>405</td>
                                <td>Method not allowed</td>
                            </tr>
                            <tr>
                                <td>429</td>
                                <td>Rate limit exceeded</td>
                            </tr>
                            <tr>
                                <td>500</td>
                                <td>Internal server error</td>
                            </tr>
                            <tr>
                                <td>503</td>
                                <td>Service unavailable</td>
                            </tr>
                        </tbody>
                    </table>
                    <hr>

                    <h4>Path Parameters</h4>
                    {{ parameters }}

                    <hr>

                    <div class="col-md-12" style="display:none;">

                        <h4>Request URL</h4>
                        <div id="request_url_{{ elt_id }}">
                            <pre></pre>
                        </div>

                        <h4>Request Headers</h4>
                        <div id="request_headers_{{ elt_id }}">
                            <pre></pre>
                        </div>

                        <h4>Response Code</h4>
                        <div id="response_code_{{ elt_id }}">
                            <pre></pre>
                        </div>

                        <h4>Response Headers</h4>
                        <div id="response_headers_{{ elt_id }}">
                            <pre></pre>
                        </div>

                        <h4>Response Body</h4>
                        <div id="response_body_{{ elt_id }}">
                            <pre></pre>
                        </div>
                    </div>
                    <pre style="display:none;" class="prettyprint" id="response{{ elt_id }}"></pre>
                </div><!-- #info -->

                <div class="tab-pane" id="sample{{ elt_id }}">
                    <div class="row">
                        <div class="col-md-12">
                            {{ sample_response }}
                        </div>
                    </div>
                </div><!-- #sample -->

            </div><!-- .tab-content -->
        </div>
    </div>
</div>';

        static $sampleReponseTpl = '
<pre id="sample_response{{ elt_id }}">{{ response }}</pre>';

        static $paramTableTpl = '
<form enctype="application/x-www-form-urlencoded" role="form" action="{{ route }}" method="{{ method }}" name="form{{ elt_id }}" id="form{{ elt_id }}">
    <table class="table table-hover">
        <thead>
            <tr>
                <th>Name</th>
                <th>Value</th>
                <th>Data Type</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            {{ tbody }}
        </tbody>
    </table>
    <button type="submit" class="btn btn-success send" rel="{{ elt_id }}">Send</button>
</form>';


        static $paramContentTpl = '
<tr>
    <td>
        {{ name }}
        <div class="required">{{ nullable }}</div>
    </td>
    <td><input type="text" class="form-control input-sm" id="{{ name }}" placeholder="{{ name }}" name="{{ name }}"></td>
    <td>{{ type }}</td>
    <td>{{ description }}</td>
</tr>';

        static $paramSampleBtnTpl = '
<a href="javascript:void(0);" data-toggle="popover" data-placement="bottom" title="Sample object" data-content="{{ sample }}">
    <i class="btn glyphicon glyphicon-exclamation-sign"></i>
</a>';

}
