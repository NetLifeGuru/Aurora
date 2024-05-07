<?php
/**
 * MIT License
 *
 * Copyright (c) 2023 NetLife Guru
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */
declare(strict_types=1);

namespace Nlg\Aurora;

use Closure;
use Exception;
use Throwable;


trait ErrorHandling
{
    /**
     * @var
     */
    protected $workingDirectory;

    /**
     * @param string $file
     * @param int $line
     * @param string $message
     * @return void
     */
    protected function catchingError(string $file, int $line, string $message): void
    {
        preg_match("/<?php\n\/\*filename:(.*?)\*\//", file_get_contents($file), $m);
        if (!empty($m[1])) {

            list($startLine, $endLine, $sourceLines) = $this->gettingErrorLinesOccurrence($file, $line);

            $errorOccurredIn = [
                "message" => "Found error: <strong>" . $file . " : " . $line . "</strong>",
                "error" => [$message],
                "source" => $this->workingDirectory . $m[1],
                "startLine" => $startLine,
                "endLine" => $endLine,
                "phpLine" => $line,
                "phpFile" => $file,
                "phpSourceLines" => $sourceLines,
            ];

            $this->displayErrorOccurrence($errorOccurredIn);
            exit;
        } else {
            $err = [$message, "<br />" . $file, "<br /> Error occurrence on the line:" . $line];
            $this->errors(500, $err);
        }
    }

    /**
     * @param int $errorLine
     * @param array $source
     * @return string
     */
    protected function codeLineListing(int $errorLine, array $source): string
    {
        $response = ['<table class="sourceCode">'];

        foreach ($source as $line => $code) {
            $code = htmlspecialchars($code);
            $response[] = "<tr" . (($errorLine == $line) ? ' class="error"' : '') . "><td>$line</td><td>$code</td></tr>";
        }
        $response[] = '</table>';

        return implode("", $response);
    }

    /**
     * @param array $arr
     * @return void
     */
    protected function displayErrorOccurrence(array $arr): void
    {
        foreach (['message', 'phpFile', 'source'] as $key) {
            $arr[$key] = preg_replace('/(\/\/+)/', '/', $arr[$key]);
        }

        $errorFile = array_merge([''], file($arr['source']));

        $tplSourceLines = [];
        if (!empty($arr["startLine"]) && !empty($arr["endLine"]))
            for ($i = $arr["startLine"]; $i <= $arr["endLine"]; $i++) {
                if (isset($errorFile[$i])) {
                    $tplSourceLines[$i] = trim($errorFile[$i]);
                }
            }

        if ($arr["startLine"] != $arr["endLine"]) {
            $line = "between lines: " . $arr["startLine"] . " - " . $arr["endLine"];
        } else {
            $line = ": " . $arr["startLine"];
        }

        foreach (['message', 'source', 'phpFile'] as $key) {
            $arr[$key] = str_replace(workingDirectory, '', $arr[$key]);
        }

        $message =
            "<p>Source of the error is in the file:</p>" .
            "<p> Found error: <strong>" . $arr["source"] . $line . "</strong></p>" .
            $this->codeLineListing((int)$arr["startLine"], $tplSourceLines) .
            "<p>" . $arr['message'] . "</p>" .
            "<p>" . implode("<br />", $arr['error']) . "</p>" .
            $this->codeLineListing((int)$arr['phpLine'], $arr['phpSourceLines']);

        print $this->template("en", "Template engine - Error Occurred", trim($message));
    }

    /**
     * @param string $file
     * @param int $line
     * @return array
     */
    protected function gettingErrorLinesOccurrence(string $file, int $line): array
    {
        $errorFile = array_reverse(array_slice(file($file), 0, $line));

        $startLine = '';
        $endLine = '';
        $source = [];
        foreach ($errorFile as $data) {
            if (str_contains($data, '/*line:')) {

                preg_match("/(\d+)/", $data, $matchSourceFileErrorLine);
                preg_match("/(\d+).-.(\d+)/", $data, $matchSourceFileErrorLines);

                if (!empty($matchSourceFileErrorLines)) {
                    $startLine = $matchSourceFileErrorLines[1];
                    $endLine = $matchSourceFileErrorLines[2];
                } else {
                    $startLine = $matchSourceFileErrorLine[1];
                    $endLine = $matchSourceFileErrorLine[1];
                }

                break;
            }
            $line--;
            $source[] = $data;
        }

        $line++;

        $lines = [];
        foreach (array_reverse($source) as $sourceLine) {
            $lines[$line++] = trim($sourceLine);
        }

        return [$startLine, $endLine, $lines];
    }


    /**
     * @param int $statusCode
     * @param array $errors
     * @return void
     */
    protected function errors(int $statusCode, array $errors): void
    {
        if (!empty($errors)) {
            $status = $this->httpStatus($statusCode);

            header($status);
            print $this->template($lang ?? 'en', $title ?? '', '<div class="center-content">' . implode("\n", $errors) . '</div>');
            exit;
        }
    }

    /**
     * @param int $status
     * @return string
     */
    public static function httpStatus(int $status = 404): string
    {
        $httpStatuses = [
            100 => '100 Continue',
            101 => '101 Switching Protocols',
            200 => '200 OK',
            201 => '201 Created',
            202 => '202 Accepted',
            203 => '203 Non-Authoritative Information',
            204 => '204 No Content',
            205 => '205 Reset Content',
            206 => '206 Partial Content',
            300 => '300 Multiple Choices',
            301 => '301 Moved Permanently',
            302 => '302 Found',
            303 => '303 See Other',
            304 => '304 Not Modified',
            305 => '305 Use Proxy',
            307 => '307 Temporary Redirect',
            400 => '400 Bad Request',
            401 => '401 Unauthorized',
            402 => '402 Payment Required',
            403 => '403 Forbidden',
            404 => '404 Not Found',
            405 => '405 Method Not Allowed',
            406 => '406 Not Acceptable',
            407 => '407 Proxy Authentication Required',
            408 => '408 Request Time-out',
            409 => '409 Conflict',
            410 => '410 Gone',
            411 => '411 Length Required',
            412 => '412 Precondition Failed',
            413 => '413 Request Entity Too Large',
            414 => '414 Request-URI Too Large',
            415 => '415 Unsupported Media Type',
            416 => '416 Requested Range Not Satisfiable',
            417 => '417 Expectation Failed',
            500 => '500 Internal Server Error',
            501 => '501 Not Implemented',
            502 => '502 Bad Gateway',
            503 => '503 Service Unavailable',
            504 => '504 Gateway Time-out',
            505 => '505 HTTP Version Not Supported',
        ];

        return ($_SERVER['SERVER_PROTOCOL'] ?? '') . ' ' . ($httpStatuses[$status] ?? $httpStatuses['404']);
    }

    /**
     * @param array $props
     * @return never
     */
    public function terminate(array $props = []): never
    {
        $title = $props['title'] ?? null;
        $content = $props['content'] ?? null;
        $header = $props['header'] ?? '404';
        $lang = $props['lang'] ?? 'en';

        $status = $this->httpStatus($header);

        header($status);

        print $this->template($lang ?? 'en', $title ?? '', $content ?? '');

        exit;
    }

    /**
     * @param string $lang
     * @param string $title
     * @param string $content
     * @return string
     */
    protected function template(string $lang = 'en', string $title = '', string $content = ''): string
    {
        return '<!DOCTYPE html>
<html lang="' . $lang . '">
<head>
	<title>' . $title . '</title>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta http-equiv="X-UA-Compatible" content="ie=edge">
	<link rel="icon" type="image/x-icon" href=" data:image/jpeg;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=">
	<link rel="preconnect" href="https://fonts.gstatic.com">
	<link href="https://fonts.googleapis.com/css2?family=Raleway:ital,wght@0,100;0,200;0,300;0,400;0,500;1,100;1,200;1,300;1,400;1,500&display=swap" rel="stylesheet"> 
	<style>
		html {
		  line-height: 2;
		}
		body {
		  margin: 0;
		  background: #f8fbfd;
		  font-family: "Raleway", sans-serif;
		  color: #5a6c83;
		}
		h1 {
		  font-size: 2em;
		  margin: 0.67em 0;
		}
		p, ul, ol, dl, img {
		  margin: 0;
		  padding: 0;
		}
		
		/* Form elements */
		button, input, select, textarea {
		  font-family: inherit;
		  font-size: 100%;
		  line-height: 1.15;
		  margin: 0;
		}
		button, input {
		  overflow: visible;
		}
		
		/* Specific element adjustments */
		details, main {
		  display: block;
		}
		hr {
		  height: 0;
		}
		code, kbd, pre, samp {
		  font-family: monospace, monospace;
		  font-size: 1em;
		}
		
		/* Links and abbreviations */
		a {
		  background-color: transparent;
		  text-decoration: none;
		}
		abbr[title] {
		  border-bottom: none;
		  text-decoration: underline;
		}
		
		/* Styling for bold and italic text */
		b, strong {
		  font-weight: bolder;
		}
		i {
		  font-family: Georgia, serif;
		  font-size: 13px;
		}
		
		/* Small, Sub and Sup adjustments */
		small {
		  font-size: 80%;
		}
		sub, sup {
		  font-size: 75%;
		  line-height: 0;
		  position: relative;
		  vertical-align: baseline;
		}
		sub {
		  bottom: -0.25em;
		}
		sup {
		  top: -0.5em;
		}
		
		/* Image styling */
		img {
		  border-style: none;
		}
		
		/* Table and pre styling */
		table.sourceCode, pre {
          border-collapse: collapse;
		  background: #fff;
		  color:#000;
		  font-size:14px;
		  margin:20px 0;
		  font-family: "Courier New", monospace;
		  border:5px solid #ffffff;
		}
		table.sourceCode {
		  width: 100%;
		  padding: 0;
		  overflow: hidden;
		}
		table.sourceCode td {
		    border-bottom: 1px solid #f7f7f7;
		}
		table.sourceCode tr.error {
            font-weight: bold;
		}
		table.sourceCode tr.error td:nth-child(2) {
		    background:#cc1313;
		    color:#fff;
		}
		table.sourceCode tr, table.sourceCode tr td {
		  padding: 0;
		  margin: 0;
		}
		table.sourceCode tr > td:nth-child(1) {
		  background: #eee;
		  padding: 10px;
		  width: 40px;
		  text-align: center;
		  font-size:12px;
		}
		table.sourceCode tr > td:nth-child(2) {
		  padding: 10px;
		}
		
		/* Layout adjustments */
		body > section {
		  float: left;
		  padding: 5%;
		  width: calc(100% - 10%);
		}
		.center-content {
		  font-size: 20px;
		  position: absolute;
		  left: 50%;
		  top: 40%;
		  transform: translate(-50%, -50%);
		}
		pre#tpl span {
		    margin-left:30px;
		}
		pre#tpl {
		    border:none;
            background:none;
		}
	</style>
</head>
<body>
	<section>
		' . $content . '
	</section>
</body>
</html>';
    }
}

interface TemplateInterface
{
    /**
     * @param string $route
     * @return void
     */
    public function setWorkingUrl(string $route): void;

    /**
     * @return string
     */
    public function getDomain(): string;

    /**
     * @param string $startingBlock
     * @return string
     */
    public function render(string $startingBlock): string;

    /**
     * @param bool $parseFiles
     * @return void
     */
    public function createCache(bool $parseFiles): void;

    /**
     * @param array $files
     * @return void
     */
    public function setFiles(array $files): void;

    /**
     * @param array $params
     * @return void
     */
    public function setVariables(array $params): void;

    /**
     * @param array $const
     * @return void
     */
    public function setLanguageConstants(array $const = []): void;

    /**
     * @param array $routes
     * @return void
     */
    public function setRoutes(array $routes = []): void;
}

interface formsInterface
{

    /**
     * @param Closure $fn
     * @return void
     */
    public function Input(Closure $fn): void;

    /**
     * @param Closure $fn
     * @return void
     */
    public function Textarea(Closure $fn): void;

    /**
     * @param Closure $fn
     * @return void
     */
    public function Select(Closure $fn): void;

    /**
     * @param Closure $fn
     * @return void
     */
    public function Radio(Closure $fn): void;

    /**
     * @param Closure $fn
     * @return void
     */
    public function Checkbox(Closure $fn): void;

    /**
     * @param string $html
     * @param array $attr
     * @return void
     */
    public function FormInput(string &$html, array $attr): void;

    /**
     * @param string $html
     * @param array $attr
     * @return void
     */
    public function FormTextarea(string &$html, array $attr): void;

    /**
     * @param string $html
     * @param array $attr
     * @return void
     */
    public function FormSelect(string &$html, array $attr): void;

    /**
     * @param string $html
     * @param array $attr
     * @return void
     */
    public function FormRadio(string &$html, array $attr): void;

    /**
     * @param string $html
     * @param array $attr
     * @return void
     */
    public function FormCheckbox(string &$html, array $attr): void;
}

interface polymorphismPatternInterface
{

}

class polymorphismPattern implements polymorphismPatternInterface
{
    /**
     * @param string $methodName
     * @param array $params
     * @param bool $passingByReference
     * @param bool $startingBlock
     * @return string
     */
    protected function renderBlock(string $methodName, array &$params, $passingByReference = false, $startingBlock = false): string
    {
        return '';
    }
}

class forms extends polymorphismPattern implements formsInterface
{

    /**
     * @var array
     */
    public static array $RewriteFormElements;

    /**
     * @param Closure $fn
     * @return void
     */
    public function Input(Closure $fn): void
    {
        self::$RewriteFormElements['Input'] = $fn;
    }

    /**
     * @param Closure $fn
     * @return void
     */
    public function Textarea(Closure $fn): void
    {
        self::$RewriteFormElements['Textarea'] = $fn;
    }

    /**
     * @param Closure $fn
     * @return void
     */
    public function Select(Closure $fn): void
    {
        self::$RewriteFormElements['Select'] = $fn;
    }

    /**
     * @param Closure $fn
     * @return void
     */
    public function Radio(Closure $fn): void
    {
        self::$RewriteFormElements['Radio'] = $fn;
    }

    /**
     * @param Closure $fn
     * @return void
     */
    public function Checkbox(Closure $fn): void
    {
        self::$RewriteFormElements['Checkbox'] = $fn;
    }

    /**
     * @param string $html
     * @param array $attr
     * @return void
     */
    public function FormInput(string &$html, array $attr): void
    {
        $input = $this->renderBlock('render_Input', $attr);
        if (empty($input)) {
            if (isset(self::$RewriteFormElements["Input"])) {
                $html .= self::$RewriteFormElements["Input"]($attr);
            } else {
                if ($attr['isHidden']) {
                    $html .= '<input type="hidden" ' . $attr['chain'] . '>';
                } else {
                    $html .= '<div class="form-control form-input' . ($attr['isInvalid'] ? ' invalid' : '') . '">';
                    $html .= '<label>' . ($attr['label'] ?? '') . ($attr['isRequired'] ? '<span class="asterisk">*</span>' : '') . '</label>';
                    $html .= '<input ' . $attr['chain'] . ' ' . ($attr['isRequired'] ? 'required' : '') . ' ' . ($attr['isReadOnly'] ? 'readonly' : '') . ' >';
                    $html .= $this->displayInfoAndError($attr);
                    $html .= '</div>';
                }
            }
        } else {
            $html = $input;
        }
    }

    /**
     * @param string $html
     * @param array $attr
     * @return void
     */

    public function FormTextarea(string &$html, array $attr): void
    {
        $textarea = $this->renderBlock('render_Textarea', $attr);
        if (empty($textarea)) {
            if (isset(self::$RewriteFormElements["Textarea"])) {
                $html = self::$RewriteFormElements["Textarea"]($attr);
            } else {
                $html .= '<div class="form-control form-textarea' . ($attr['isInvalid'] ? ' invalid' : '') . '">';
                $html .= '<label>' . ($attr['label'] ?? '') . ($attr['isRequired'] ? '<span class="asterisk">*</span>' : '') . '</label>';
                $html .= '<textarea ' . $attr['chain'] . ' ' . ($attr['isRequired'] ? 'required' : '') . ' ' . ($attr['isReadOnly'] ? 'readonly' : '') . ' >' . $attr['value'] . '</textarea>';
                $html .= $this->displayInfoAndError($attr);
                $html .= '</div>';
            }
        } else {
            $html = $textarea;
        }
    }

    /**
     * @param string $html
     * @param array $attr
     * @return void
     */
    public function FormSelect(string &$html, array $attr): void
    {
        $select = $this->renderBlock('render_Select', $attr);
        if (empty($select)) {
            if (isset(self::$RewriteFormElements["Select"])) {
                $html = self::$RewriteFormElements["Select"]($attr);
            } else {
                $html .= '<div class="form-control form-select' . ($attr['isInvalid'] ? ' invalid' : '') . '">';
                $html .= '<label>' . ($attr['label'] ?? '') . ($attr['isRequired'] ? '<span class="asterisk">*</span>' : '') . '</label>';
                $html .= '<select ' . $attr['chain'] . ' ' . ($attr['isRequired'] ? 'required' : '') . ' ' . ($attr['isReadOnly'] ? 'readonly' : '') . ' />';
                if (!empty($attr['data'])) {
                    foreach ($attr['data'] as $value => $title) {
                        $html .= '<option value="' . $value . '" ' . ((!empty($attr['selected']) && $attr['selected'] == $value) ? 'selected' : '') . '>' . $title . '</option>';
                    }
                }
                $html .= '</select>';
                $html .= $this->displayInfoAndError($attr);
                $html .= '</div>';
            }
        } else {
            $html = $select;
        }
    }

    /**
     * @param string $html
     * @param array $attr
     * @return void
     */
    public function FormRadio(string &$html, array $attr): void
    {
        $radio = $this->renderBlock('render_Radio', $attr);
        if (empty($radio)) {
            if (isset(self::$RewriteFormElements["Radio"])) {
                $html = self::$RewriteFormElements["Radio"]($attr);
            } else {
                $html .= '<div class="form-control' . ($attr['isInvalid'] ? ' invalid' : '') . '">';

                $html .= '<label>' . ($attr['label'] ?? '') . ($attr['isRequired'] ? '<span class="asterisk">*</span>' : '') . '</label>';
                if (!empty($attr['data'])) {
                    foreach ($attr['data'] as $value => $title) {
                        $html .= '<div class="form-radio">';
                        $html .= '<input name="' . $attr['name'] . '" type="radio" value="' . $value . '" ' . (($attr['selected'] == $value) ? 'checked' : '') . ' />' . $title;
                        $html .= '</div>';
                    }
                }
                $html .= $this->displayInfoAndError($attr);

                $html .= '</div>';
            }
        } else {
            $html = $radio;
        }
    }

    /**
     * @param string $html
     * @param array $attr
     * @return void
     */
    public function FormCheckbox(string &$html, array $attr): void
    {
        $checkbox = $this->renderBlock('render_Checkbox', $attr);
        if (empty($checkbox)) {
            if (isset(self::$RewriteFormElements["Checkbox"])) {
                $html = self::$RewriteFormElements["Checkbox"]($attr);
            } else {
                $html .= '<div class="form-control form-checkbox' . ($attr['isInvalid'] ? ' invalid' : '') . '">';
                $html .= '<input type="checkbox" ' . $attr['chain'] . ' ' . ($attr['isRequired'] ? 'required' : '') . ' ' . ($attr['isReadOnly'] ? 'readonly' : '') . ' ' . ($attr['isChecked'] ? 'checked' : '') . ' />';
                $html .= '<label>' . ($attr['label'] ?? '') . ($attr['isRequired'] ? '<span class="asterisk">*</span>' : '') . '</label>';
                $html .= $this->displayInfoAndError($attr);
                $html .= '</div>';
            }
        } else {
            $html = $checkbox;
        }
    }

    /**
     * @param array $attr
     * @return string
     */
    protected
    function displayInfoAndError(array $attr): string
    {
        $html = '';
        if ($attr['isInvalid'] && !empty($attr['error'])) {
            $html .= '<span class="error">' . $attr['error'] . '</span>';
        }

        if ($attr['info'] ?? false) {
            $html .= '<span class="info">' . $attr['info'] . '</span>';
        }
        return $html;
    }
}

interface templateMacrosInterface
{
    /**
     * @param array|string $value
     * @return string
     */
    public function Print($value): string;

    /**
     * @param string $value
     * @param int $num
     * @return string
     */
    public function Trunc(string $value, int $num): string;

    /**
     * @param $value
     * @return string
     */
    public function Lang($value): string;

    /**
     * @param string $value
     * @return string
     */
    public function HtmlChars(string $value): string;

    /**
     * @param string $url
     * @return string
     */
    public function IsActive(string $url): string;

    /**
     * @param string $filePath
     * @return string
     */
    public function Version(mixed $filePath): mixed;

    /**
     * @param string $content
     * @return string
     */
    public function Escape(string $content): string;

    /**
     * @param string $date
     * @param string $format
     * @return string
     */
    public function DateFormat(string $date, string $format): string;

    /**
     * @param string $text
     * @return string
     */
    public function Upper(string $text): string;

    /**
     * @param string $text
     * @return string
     */
    public function Lower(string $text): string;

    /**
     * @param string $text
     * @return string
     */
    public function Title(string $text): string;

    /**
     * @param mixed $condition
     * @param string $class
     * @return string
     */
    public function IfClass(mixed $condition, string $class): string;

    /**
     * @param mixed $condition
     * @param string $class
     * @return string
     */
    public function IfEval(mixed $condition, string $class): string;

    /**
     * @param mixed $value
     * @param mixed $comparisonValue
     * @param string $class
     * @return string
     */
    public function CompareClass(mixed $value, mixed $comparisonValue, string $class): string;

    /**
     * @param mixed $value
     * @param mixed $comparisonValue
     * @param string $class
     * @return string
     */
    public function Compare(mixed $value, mixed $comparisonValue, string $class): string;

    /**
     * @param float $value
     * @return float
     */
    public function abs(float $value): float;

    /**
     * @param array $numbers
     * @return float
     */
    public function max(array $numbers): float;

    /**
     * @param array $numbers
     * @return float
     */
    public function min(array $numbers): float;

    /**
     * @param float $number
     * @return float
     */
    public function RoundUp(float $number): float;

    /**
     * @param float $number
     * @return float
     */
    public function RoundDown(float $number): float;

    /**
     * @param array $arr
     * @return array
     */
    public function Sort(array $arr): array;

    /**
     * @param array $arr
     * @return array
     */
    public function KeySort(array $arr): array;

    /**
     * @param array $arr
     * @return array
     */
    public function Values(array $arr): array;

    /**
     * @param array $arr
     * @return array
     */
    public function Unique(array $arr): array;

    /**
     * @param array $arr
     * @return array
     */
    public function Keys(array $arr): array;

    /**
     * @param array $arr
     * @param mixed $search
     * @return mixed
     */
    public function Search(array $arr, mixed $search): mixed;

}

class templateMacros extends forms implements templateMacrosInterface
{
    use ErrorHandling;

    public function __construct()
    {
        $macroClass = 'Macros';

        if (class_exists($macroClass) && empty($this->macros)) {
            $this->macros = new $macroClass();
        }
    }

    /**
     * @var array
     */
    public array $formAttributes = [
        'FormInput' => ['id', 'class', 'value', 'type', 'title', 'placeholder', 'info', 'data', 'aria-label', "rel"],
        'FormTextarea' => ['id', 'class', 'rows', 'cols', 'type', 'title', 'placeholder', 'info', 'data', 'aria-label', 'rel'],
        'FormSelect' => ['id', 'class', 'title', 'placeholder', 'info', 'aria-label', 'rel'],
        'FormRadio' => [],
        'FormCheckbox' => ['id', 'class', 'value', 'title', 'placeholder', 'info', 'data', 'aria-label', 'rel']
    ];

    public $macros;

    /**
     * @param $method
     * @param $args
     * @return string
     * @throws Exception
     */
    public function __call($method, $args): mixed
    {
        $html = '';
        $method = preg_replace("/^macro/", "", $method);

        if ($this->macros != null && method_exists($this->macros, $method)) {

            $html = $this->macros->{$method}(...$args);

        } else if (method_exists($this, $method)) {

            if (in_array($method, ['FormInput', 'FormTextarea', 'FormSelect', 'FormRadio', 'FormCheckbox'])) {

                $r = [];
                foreach ($this->formAttributes[$method] as $name) {
                    $attr = $args[0] ?? [];
                    if (!empty($attr[$name])) {
                        $r[] = $name . '="' . $attr[$name] . '"';
                    }
                }

                $args[0]['chain'] = implode(" ", $r);

                $this->{$method}($html, ...$args);

            } else {
                $html = $this->{$method}(...$args);
            }

        } else {

            throw new Exception(debug_backtrace()[0]);

        }

        return $html;
    }

    /**
     * @var array
     */
    public array $lang;

    /**
     * @param $value
     * @return string
     */
    public function Lang($value): string
    {
        return $this->lang[$value] ?? $value;
    }

    /**
     * @param array|string $value
     * @return string
     */
    public function Print($value): string
    {
        if ($value === null) {
            $value = "";
        }

        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                $result[] = '  [' . $key . ' => ' . $item . ']';
            }
            return "<pre>[<br />" . implode(",<br />", $result) . "<br />]</pre>";
        }
        return (string)$value;
    }


    /**
     * @param string $value
     * @return string
     */
    public function HtmlChars(string $value): string
    {
        return htmlspecialchars($value);
    }

    public function IsActive(string $url): string
    {
        $url = trim($url, "/");

        $uri = $_SERVER['REQUEST_URI'];
        $path = parse_url($uri, PHP_URL_PATH);
        $segments = explode('/', trim($path, '/'));

        $currentUrl = trim(!empty($segments[0]) ? $segments[0] : "");

        if ($url === $currentUrl) {
            return 'active';
        }

        return '';
    }

    /**
     * @param string $filePath
     * @return string
     */
    public function Version(mixed $filePath): mixed
    {
        $path = getcwd() . '/' . $filePath;

        if (file_exists($path)) {
            $filePath .= '?v=' . filemtime($path);
        }

        return $filePath;
    }

    /**
     * Escapes different types of content based on the context.
     *
     * @param string $content The content to escape.
     *
     * @return string The escaped content.
     */
    public function Escape(string $content): string
    {
        return htmlspecialchars(strip_tags($content), ENT_QUOTES, 'UTF-8');
    }

    /**
     * @param string $value
     * @param int $num
     * @return string
     */
    public function Trunc(string $value, int $num): string
    {
        return substr(strip_tags($value), 0, $num);
    }

    /**
     * @param string $text
     * @return string
     */
    public function Upper(string $text): string
    {
        return strtoupper($text);
    }

    /**
     * @param string $text
     * @return string
     */
    public function Lower(string $text): string
    {
        return strtolower($text);
    }

    /**
     * @param string $text
     * @return string
     */
    public function Title(string $text): string
    {
        return ucfirst($text);
    }

    /**
     * @param string $date
     * @param string $format
     * @return string
     */
    public function DateFormat(string $date, string $format = 'Y-m-d H:i:s'): string
    {
        return date($format, strtotime($date));
    }

    /**
     * @param mixed $condition
     * @param string $class
     * @return string
     */
    public function IfClass(mixed $condition, string $class): string
    {
        return $condition ? 'class="' . trim($class, "\"' ") . '"' : '';
    }

    /**
     * @param mixed $condition
     * @param string $class
     * @return string
     */
    public function IfEval(mixed $condition, string $class): string
    {
        return $condition ? trim($class, "\"' ") : '';
    }

    /**
     * @param mixed $value
     * @param mixed $comparisonValue
     * @param string $class
     * @return string
     */
    public function CompareClass(mixed $value, mixed $comparisonValue, string $class): string
    {
        return ($value == $comparisonValue) ? 'class="' . trim($class, "\"' ") . '"' : '';
    }

    /**
     * @param mixed $value
     * @param mixed $comparisonValue
     * @param string $class
     * @return string
     */
    public function Compare(mixed $value, mixed $comparisonValue, string $class): string
    {
        return ($value == $comparisonValue) ? trim($class, "\"' ") : '';
    }

    /**
     * @param float $value
     * @return float
     */
    public function abs(float $value): float
    {
        return abs($value);
    }

    /**
     * @param array $numbers
     * @return float
     */
    public function max(array $numbers): float
    {
        return max($numbers);
    }

    /**
     * @param array $numbers
     * @return float
     */
    public function min(array $numbers): float
    {
        return min($numbers);
    }

    /**
     * @param float $number
     * @return float
     */
    public function RoundUp(float $number): float
    {
        return ceil($number);
    }

    /**
     * @param float $number
     * @return float
     */
    public function RoundDown(float $number): float
    {
        return floor($number);
    }

    /**
     * @param array $arr
     * @return array
     */
    public function Sort(array $arr): array
    {
        sort($arr);
        return $arr;
    }

    /**
     * @param array $arr
     * @return array
     */
    public function KeySort(array $arr): array
    {
        ksort($arr);
        return $arr;
    }

    /**
     * @param array $arr
     * @return array
     */
    public function Values(array $arr): array
    {
        return array_values($arr);
    }

    /**
     * @param array $arr
     * @return array
     */
    public function Unique(array $arr): array
    {
        return array_unique($arr);
    }

    /**
     * @param array $arr
     * @return array
     */
    public function Keys(array $arr): array
    {
        return array_keys($arr);
    }

    /**
     * @param array $arr
     * @param mixed $search
     * @return float|string|int|false
     */
    public function Search(array $arr, mixed $search): float|string|int|false
    {
        return array_search($search, $arr);
    }
}

class Loader extends templateMacros implements TemplateInterface
{
    /**
     * @var mixed
     */
    public $val;
    /**
     * @var mixed|string
     */
    protected $workingDirectory;
    /**
     * @var
     */
    private $workingFiles;
    /**
     * @var
     */
    protected $languageConstants;
    /**
     * @var
     */
    private $routes;
    /**
     * @var mixed|string
     */
    private $cacheDirectory;
    /**
     * @var
     */
    private $templateVariables = [];
    /**
     * @var array
     */
    private static array $templateInstances = [];
    /**
     * @var array
     */
    private array $reset = [];
    /**
     * @var $workingUrl
     */
    public $workingUrl;
    /**
     * @var bool
     */
    protected $productionMode = true;

    /**
     * @param array $setup
     */
    public function __construct(array $setup = [])
    {
        parent::__construct();

        if (!defined('workingDirectory')) {
            $pathElements = array_intersect(explode("/", __DIR__), explode("/", getcwd()));
            $array = array_diff(range(0, max(array_keys($pathElements))), array_keys($pathElements));
            $commonPath = implode("/", array_slice($pathElements, 0, array_shift($array)));
            $path = realpath($setup['root'] ?? $commonPath);
            define('workingDirectory', $path);
        }

        /**
         * Setting up working s directories
         */

        $this->cacheDirectory = $this->fixPath($setup['cache'] ?? '/cache/');
        $this->workingDirectory = $this->fixPath($setup['views'] ?? '/views/');
    }

    /**
     * @param string $path
     * @return string
     */
    private function fixPath(string $path): string
    {
        return preg_replace('"/+"', '/', workingDirectory . DIRECTORY_SEPARATOR . trim(preg_replace('/^(\.\.\/|\.\/)/', '', ($path)), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
    }

    public function setupDirectories(): void
    {
        if (PHP_OS === "Linux" || PHP_OS === "Darwin") {
            $fileOwnerInfo = posix_getpwuid(fileowner(workingDirectory))['name'];
            $currentUserName = posix_getpwuid(posix_geteuid())['name'];

            if ($fileOwnerInfo !== $currentUserName) {
                $this->errors(500, [
                    'title' => '<h1>Ownership Change Required</h1>',
                    'content' => '
                        Please change the files and directories owner. 
                        <br />
                        <strong>You can use the next source code in your project directory</strong>
<pre>
<code>
    #!/bin/bash
    OS=$(uname)
    if [ "$OS" = "Linux" ]; then
        chown -R www-data:www-data $(pwd)
        chmod -R 755 $(pwd)
    elif [ "$OS" = "Darwin" ]; then
        chown -R $(whoami):staff $(pwd)
        chmod -R 755 $(pwd)
    fi
    </code>
</pre>
                       ',
                ]);
                exit;
            }
        }

        if (!is_writable(getcwd())) {
            chmod(getcwd(), 0755);
        }

        if (!is_dir($this->cacheDirectory)) {
            mkdir($this->cacheDirectory, 0755);
            chmod($this->cacheDirectory, 0755);
        }

        if (!is_dir($this->workingDirectory)) {
            mkdir($this->workingDirectory, 0755);
            chmod($this->workingDirectory, 0755);
        }

        chmod($this->cacheDirectory, 0744);
        chmod($this->workingDirectory, 0744);
    }

    /**
     * @param bool $parseFiles
     * @return void
     */
    public function createCache(bool $parseFiles = false): void
    {
        $this->productionMode = !$parseFiles;

        $this->setupDirectories();

        $err = [];
        $fileContents = [];
        $excludedSource = [];

        $this->workingFiles = $this->gettingWorkingFiles();

        if (empty($this->workingFiles)) {
            if (!$this->productionMode) {
                $err[] = "No input template files are specified for processing!";
            }
        }

        foreach ($this->workingFiles as $file) {
            $fullPath = $this->workingDirectory . trim($file, '/');
            if (preg_match('/([^\w\/._-]+)/', $file)) {
                $err[] = $file . ": path contains illegal characters!";
            } else if (!file_exists($fullPath)) {
                $err[] = $file . " file doesn't exist!<br />";
            } else {

                $content = file_get_contents($fullPath);

                foreach (['pre', 'script', 'noscript', 'style'] as $val) {
                    /**
                     * Removing all inline scripts from the source file
                     */
                    preg_match_all("/<$val(.*?)>(.*?)<\/$val>/s", $content, $m, PREG_SET_ORDER);

                    foreach ($m as $arr) {

                        if (!empty($arr[2])) {
                            if (str_contains($arr[2], 'src=')) continue;

                            $i = count($excludedSource);
                            $key = "/*source$i*/";

                            if ($val === 'pre') {

                                $excludedSource[$key] = '<pre><code' . $arr[1] . '>' . htmlspecialchars($arr[2]) . '</code></pre>';

                            } else {

                                $excludedSource[$key] = $arr[0];

                            }

                            $lines = str_repeat(PHP_EOL, substr_count($arr[0], PHP_EOL));
                            $content = str_replace($arr[0], $key . $lines, $content);
                        }
                    }
                }

                $fileContents[$file] = ['content' => $content, 'checksum' => md5_file($fullPath)];
            }
        }

        $this->errors(500, $err ?? []);

        $this->parseFileContents($fileContents, $parseFiles, $excludedSource);
    }

    /**
     * @param string $source
     * @return string
     *
     * Replacing all {view} <div>html</div> {/view} to $html .= '<div>html</div>';
     */
    private function buildSourceCode(string $source): string
    {
        preg_match_all('/{view}(.*?){\/view}/s', $source, $viewMatches, PREG_SET_ORDER);

        if (!empty($viewMatches)) {
            $replace = [];
            foreach ($viewMatches as $view) {
                $html = trim($view[1]);
                if (!empty($html)) {
                    $html = "\n\n\t\$html .= '" . str_replace("'", "\'", $view[1]) . "';\n";
                }
                $replace[$view[0]] = $html;
            }
            $source = strtr($source, $replace);
        }

        return $source;
    }

    /**
     * @param string $content
     * @return string
     *
     * resolving all available include{snippet} to the {block|snippet}
     */
    private function resolveInclude(string $content): string
    {
        preg_match_all('/include{(.*?)}/', $content, $includeMatches, PREG_PATTERN_ORDER);

        if (!empty($includeMatches[1])) {
            foreach ($includeMatches[1] as $include) {
                $content = str_replace('include{' . $include . '}', '{block|' . $include . '}', $content);
            }
        }

        preg_match_all('/import{(.*?)}/', $content, $includeMatches, PREG_PATTERN_ORDER);

        if (!empty($includeMatches[1])) {
            foreach ($includeMatches[1] as $include) {
                $content = str_replace('import{' . $include . '}', '{block|reference_' . $include . '}', $content);
            }
        }

        return $content;
    }

    /**
     * @param string $variable
     * @return string
     */
    private function resolveResetVariable(string $variable): string
    {
        $cleanVariable = preg_replace("/\[\s*]$/", '', $variable);

        return "\t" . $cleanVariable . ' = ' . trim($cleanVariable) . ' ?? ' . (($variable !== $cleanVariable) ? '[]' : 'null') . ';';
    }

    /**
     * @param string $snippet
     * @return array
     */
    private function resolveSnippetVariables(string $snippet): array
    {

        if (preg_match("/^`([\s\S]+)`$/m", $snippet, $m)) {
            //language
            return [$snippet => '$this->macroLang("' . str_replace('"', '\"', $m[1]) . '")'];
        }

        /**
         * getting all arrays
         */
        preg_match_all('/(\$\w+->\w+|\$\w+)(\[.*?])/sm', $snippet, $arrays, PREG_SET_ORDER);

        /**
         * getting all variables
         */
        preg_match_all('/(\$\w+->\w+|\$\w+)(?!=.*\[)+/sm', preg_replace('/(?<![=>])=(?!=)/', ' = ', $snippet), $values, PREG_SET_ORDER);

        /**
         * Merging Arrays and Variables to one Array
         */
        $arr = array_merge($arrays, $values);
        if (!empty($arr)) {
            foreach ($arr as $variable) {
                preg_match_all('/\w+/', $variable[1], $match, PREG_PATTERN_ORDER);
                if (!empty($match[0])) {

                    if (empty($variable[2])) {

                        /**
                         * 2 - parameter represents variable
                         *          original variable: $variable
                         *          extracted: $variable
                         */

                        $newVariable = "\$this->val['" . implode("']['", $match[0]) . "']";
                    } else {
                        /**
                         * 3 - parameters represents array
                         *          original variable: $variable['parameter'][]
                         *          extracted: $variable
                         *          extracted: ['parameter'][]
                         */

                        $newVariable = "\$this->val['" . implode("']['", $match[0]) . "']$variable[2]";
                    }

                    $this->reset[$newVariable] = $this->resolveResetVariable($newVariable);

                    /**
                     * if snippet and extracted value from the pattern is equal, for example
                     * {$this->a}
                     * and in snippet is not any additional source code
                     * than variable will be printed
                     */

                    if ($snippet == $variable[0] . ";") {
                        $replace[$variable[0]] = "\$html .= \$this->macroPrint($newVariable)";
                    } else {
                        $replace[$variable[0]] = $newVariable;
                    }
                }
            }
        } else if (preg_match("/^[^|{}]*$/", $snippet)) {
            return [$snippet => "'" . trim($snippet, '\'"') . "'"];
        }

        return $replace ?? [];
    }

    /**
     * @param string $snippet
     * @return string
     */
    private function excludeQuotationMarks(string $snippet): string
    {
        return preg_replace("/[\"|']([\s\S]+)[\"|']/", '', $snippet);
    }

    /**
     * @param string $snippet
     * @return string
     *
     * main function for building new template, from prepared snippet
     *  - array
     *  - variables
     *  - macros
     *  - forms
     */
    private function resolveSource(string $snippet): string
    {
        $replace = [];

        /**
         * closing brackets
         */

        $snippet = preg_replace('/^\/\w+/m', '}', $snippet);

        /**
         * replace all include{content} or import{content} changed to {block|content}
         *      $html .= $this->render_content($v);
         */
        preg_match_all('/block\|(\w+)/m', $snippet, $blocks, PREG_SET_ORDER);


        if (!empty($blocks)) {

            $ref = $blocks[0][1];
            $blocks[0][1] = str_replace('reference_', '', $ref);

            if ($blocks[0][1] == $ref) {
                $replace[$blocks[0][0]] = "\t\$html .= \$this->renderBlock('render_" . $blocks[0][1] . "',\$this->val, true);";
            } else {
                $replace[$blocks[0][0]] = "\t\$html .= \$this->renderBlock('render_" . $blocks[0][1] . "',\$this->val);";
            }
        }

        /**
         * find foreach, if, else if
         */

        preg_match_all('/(foreach|else\s*if|else|if)(.*)/m', $this->excludeQuotationMarks($snippet), $controlStructures, PREG_SET_ORDER);

        if (!empty($controlStructures)) {
            $r = [];
            foreach ($controlStructures as $construct) {

                /**
                 * foreach|else|elseif|if
                 */
                $controlStructure = trim($construct[1]);
                /**
                 * structure parameter ($arr as $key => $val)
                 */
                $condition = trim($construct[2]);

                if ($controlStructure == 'else') {
                    /**
                     * changing of the simple "else" to the "\} else \{"
                     */
                    $r[$construct[0]] = "} $controlStructure {";
                } else {
                    if (preg_match('/^else\s*if/', $controlStructure)) {
                        /**
                         * fix structure elseif to "\} else if \{"
                         */
                        $controlStructure = "} $controlStructure";
                    }

                    preg_match('/(\$\w+->\w+|\$\w+)/', $condition, $match);

                    if ($controlStructure === 'foreach') {
                        if (!empty($match[0])) {
                            $r[$construct[0]] = "if (is_array(" . $match[0] . ")) \n" . $controlStructure . " ($condition) {";
                        }
                    } else {
                        if (!empty($match[0]) && !str_contains($controlStructure, 'else')) {
                            $r[$construct[0]] = $controlStructure . " (!empty(" . $match[0] . ") && $condition) {";
                        } else {
                            $r[$construct[0]] = " \n" . $controlStructure . " ($condition) {";
                        }
                    }
                }
            }

            $snippet = strtr($snippet, $r);
        } else if (!preg_match('/^block|}$/m', $snippet) && !empty($snippet) && !str_ends_with(trim($snippet), ";")) {
            $snippet = "$snippet;";
        }

        $r = $this->resolveSnippetVariables($snippet);
        $replace = array_merge($replace, $r);

        return strtr($snippet, $replace);
    }

    /**
     * @var array
     */
    public array $snippetInSourceOccurrences = [];
    /**
     * @var int
     */
    public $lastLine = 0;
    /**
     * @var string
     */
    public $fileContent = "";

    /**
     * @param string $fileContent
     * @param string $source
     * @return string
     */
    private function sourceCodeLines(string $fileContent, string $source): string
    {
        if (!isset($this->snippetInSourceOccurrences[$source]) && !preg_match("/^\//", $source)) {
            if (count(explode("\n", $source)) === 1) {
                /**
                 * Searching single line occurrences
                 */
                $l = explode("\n", $fileContent);
                foreach ($l as $lineNumber => $line) {
                    if (str_contains($line, '{' . $source . '}')) {
                        $this->snippetInSourceOccurrences[$source][] = $lineNumber + 1;
                    }
                }
            } else {
                /**
                 * Searching multi line occurrences
                 */
                $pos = strpos($this->fileContent, $source);

                if ($pos !== false) {
                    $this->fileContent = substr_replace($this->fileContent, "<------>" . str_repeat(PHP_EOL, substr_count($source, PHP_EOL)), $pos, strlen($source));

                    $lines = explode("\n", $this->fileContent);

                    $lineNumber = 1;
                    foreach ($lines as $line) {
                        if (str_contains($line, "<------>")) {
                            $this->snippetInSourceOccurrences[$source][] = $lineNumber;
                            break;
                        }
                        $lineNumber++;
                    }

                    $this->fileContent = str_replace('<------>', '', $this->fileContent);
                }
            }
        }

        if (!empty($this->snippetInSourceOccurrences[$source])) {
            $key = array_key_first($this->snippetInSourceOccurrences[$source]);
            $line = $this->snippetInSourceOccurrences[$source][$key];
            unset($this->snippetInSourceOccurrences[$source][$key]);
            if ($this->lastLine !== $line) {
                $this->lastLine = $line;
                return "\n\t/*line: " . $line . "*/\n";
            }
        }

        return '';
    }

    /**
     * @param string $source
     * @param string $sourceCode
     * @return string
     */
    private function createMacro(string $source, string $sourceCode): string
    {
        $r = '';

        /**
         * Function createMacro extracting everything between curly braces {}
         * this function will decide for what is added source use for
         * regular expression "/^`([\s\S]+)`$/m" is checking snippet for occurrence of string in form `Hello World` because `` defining language constant
         *  - language
         *  - ternary operator
         *  - forms
         *  - macros
         */

        if (preg_match("/^`([\s\S]+)`$/m", $source, $m)) {
            //language
            return '$html .= $this->macroLang("' . str_replace('"', '\"', $m[1]) . '");';
        } else if (preg_match("/(.*?)\?(.*):(.*)|(.*)\?(.*)/", $this->excludeQuotationMarks(str_replace(["\n", "\r"], " ", $source)))) {
            // ternary operator
            preg_match("/(.*?)\?(.*):(.*)|(.*)\?(.*)/", str_replace(["\n", "\r"], " ", $source), $m);
            $m = array_values(array_filter($m));
            $m[2] = $m[2] ?? "''";
            $m[3] = $m[3] ?? "''";

            $replaceSingleQuotes = ['\'' => "\\'"];

            foreach ($m as $key => $value) {

                $value = trim($value);

                if (!str_starts_with($value, "'")) {
                    $r = $this->resolveSnippetVariables($value);
                }

                if ($value != "''") {
                    $value = strtr($value, $replaceSingleQuotes);
                }

                if (!empty($r)) {
                    $m[$key] = trim(strtr($value, $r));
                }


                if ($key > 0) {
                    preg_match_all('/{(.*?)}/s', $value, $sourceMatches, PREG_PATTERN_ORDER);

                    if (!empty($sourceMatches)) {
                        $replace = [];
                        foreach ($sourceMatches[0] as $sourceMatch) {
                            $trimmedSourceMatch = trim($sourceMatch, '{}');
                            preg_match("/^`([\s\S]+)`$/m", $trimmedSourceMatch, $langMatch);
                            $r = $this->resolveSnippetVariables($value);

                            if (!empty($langMatch[1])) {
                                $replace[$sourceMatch] = "'" . ' . $this->macroLang("' . str_replace('"', '\"', $langMatch[1]) . '") . ' . "'";
                            } else {
                                $replace[$sourceMatch] = "' . " . $r[$trimmedSourceMatch] . " .  '";
                            }
                        }

                        if (!empty($replace)) {
                            $m[$key] = "'" . strtr($value, $replace) . "'";
                        }
                    }
                }
                $r = [];
            }

            return '$html .= (' . trim($m[1]) . ') ? ' . $m[2] . ':' . $m[3] . ';';

        } else if (preg_match("/^(input|textarea|select|checkbox|radio)(.*)/m", str_replace(["\n", "\r"], " ", $source), $formMatch)) {
            //forms

            /**
             * Forms will being extracted here
             * the regular expression "/^(input|textarea|select|checkbox|radio)(.*)/m" is extracting form snippets from source in form {input name="test" value="1" isInvalid}
             */

            $data = trim($formMatch[2]);

            $boolParameters['isInvalid'] = (int)str_contains($data, 'isInvalid');
            $boolParameters['isRequired'] = (int)str_contains($data, 'isRequired');
            $boolParameters['isHidden'] = (int)str_contains($data, 'isHidden');
            $boolParameters['isReadOnly'] = (int)str_contains($data, 'isReadOnly');
            $boolParameters['isChecked'] = (int)str_contains($data, 'isChecked');

            // .\w+(=(.*?))
            preg_match_all("/(\w+)\s*=\s*(['\"])(.*?)\\2/", $data, $matches);

            $attributes = [];
            for ($i = 0; $i < count($matches[1]); $i++) {
                $attrName = $matches[1][$i];
                $attrValue = $matches[3][$i];
                $attributes[$attrName] = '"' . $attrValue . '"';
            }

            $attributes = array_merge($attributes, $boolParameters);

            /**
             * resolving snippets from source and defining variables and converting variables to the template engine variables
             * for fixing variables is calling function resolveSnippetVariables
             */
            foreach (["id", "class", "label", "error", "type", "value", "title", "placeholder", "info", "selected", "checked", "data", "aria-label", "rel"] as $name) {
                if (!empty($attributes[$name])) {
                    $n = trim($attributes[$name], '"');
                    $arr = $this->resolveSnippetVariables($n);

                    if (isset($arr[$n])) {
                        $attributes[$name] = trim($arr[$n]);
                    } else {
                        $attributes[$name] = trim($attributes[$name]);
                    }
                }
            }

            $response = [];
            foreach ($attributes as $name => $value) {
                $response[] = '"' . $name . '" => ' . $value . ',';
            }

            return '$html .= $this->macroForm' . ucfirst($formMatch[1]) . "([\n" . implode("\n", $response) . "\n]);";

        } else if (!str_starts_with($source, "block") && preg_match('/(\$\w+.?=.?)?(\w+(?:\|\w+)*\|\$?[\S\s]+)/', $source, $match)) {

            // macros
            /**
             * this is the part of code which is ensuring converting {print|$value} to the callable macro
             * regular expression /\w+(?:\|\w+)*\|\$?\S+/ is searching for occurrence of the char | between curly braces {} and last of the chain string should import a variable in form $value
             */

            $fn = explode("|", $match[2]);

            $c = count($fn) - 1;
            for ($i = 0; $i < $c; $i++) {
                $r .= '$this->macro' . $fn[$i] . '(';
            }
            $value = trim(end($fn));

            $macrosInputs = [];
            foreach (explode(",", $value) as $v) {
                $arr = $this->resolveSnippetVariables($v);
                $newValue = trim($arr[$v] ?? '');
                if (is_numeric(trim($newValue, "'"))) {
                    $newValue = trim($newValue, "' ");
                }
                $macrosInputs[] = $newValue;
            }

            $r .= implode(',', $macrosInputs);
            $r .= str_repeat(')', $c);

            $l = '';
            foreach ($macrosInputs as $macrosInput) {
                if (preg_match('/^\$/', $macrosInput)) {
                    $l = "\n\t" . $macrosInput . ' = (is_array($value) ? $value: ' . $macrosInput . ');';
                    break;
                }
            }

            $reset = trim($match[1] ?? '', ' =');
            if (!empty($reset)) {
                $resetArr = $this->resolveSnippetVariables($reset);

                return "\n\t" . $resetArr[$reset] . ' = ' . $r . ';';

            } else {

                return '$html .= (($value = ' . $r . ') && !is_array($value) ? $value: \'\');' . $l;

            }

        } else {

            return $sourceCode;

        }
    }

    /**
     * @param string $fileContent
     * @param string $content
     * @return string
     *
     * preparing source for building, separation of HTML blocks into variables, and source blocks
     *
     * <div>{$this->foo}</div> into $html\ .= '<div>'; {$this->foo} $html .= '</div>';
     */
    private function resolveSourceSnippets(string $fileContent, string $content): string
    {
        preg_match_all('/{(?:[^{}]|{[^{}]*})*}/', $content, $sourceMatches, PREG_PATTERN_ORDER);

        $newSource = '{view}' . $content . '{/view}';

        $this->fileContent = $fileContent;

        if (!empty($sourceMatches[0])) {
            foreach ($sourceMatches[0] as $source) {
                $source = trim($source, '{}');

                $sourceCode = $this->resolveSource($source);
                $sourceCode = $this->createMacro($source, $sourceCode);
                $line = $this->sourceCodeLines($fileContent, $source);

                $replace = "{/view}" . $line . "\t" . $sourceCode . "{view}";
                $search = '{' . $source . '}';

                $pos = strpos($newSource, $search);
                if ($pos !== false) {
                    $newSource = substr_replace($newSource, $replace, $pos, strlen($search));
                }
            }
        }

        return $newSource;
    }

    /**
     * @param string $fileContent
     * @param string $name
     * @param array $contents
     * @return string
     */
    private function createNewFunction(string $fileContent, string $name, array $contents): string
    {
        $content = implode("\n", $contents);

        $function = "public function render_" . $name . "(): string \n{\n\n\t\n{content}\n}\n\n";

        preg_match_all('/data-(?:if|foreach|array|switch)={(.*?)}/', $content, $match, PREG_SET_ORDER);

        $content = $this->resolveInclude($content);
        $content = $this->resolveSourceSnippets($fileContent, $content);
        $content = $this->buildSourceCode($content);

        $pos = strpos($content, '$html .=');

        if ($pos !== false) {

            $check = explode('$html .=', $content)[0];

            if (preg_match('/(if|else|foreach|\{)/', $check)) {

                $content = "\$html = '';\n" . $content . "\n\treturn \$html;";


            } else {
                preg_match_all('/\$html \.=/', $content, $m);

                $i = count($m[0]);

                if ($i == 1) {
                    $content = substr_replace($content, 'return', $pos, strlen('$html .=')) . "";
                } else {
                    $content = substr_replace($content, '$html =', $pos, strlen('$html .=')) . "";
                    $content .= "\n\treturn \$html;";
                }
            }

        } else {
            $content .= "\n\treturn ' ';";
        }

        return strtr($function, [
            '{content}' => $content
        ]);
    }

    /**
     * @param string $fileName
     * @return string
     */
    private function createClassNameFromFileName(string $fileName): string
    {
        return 'renderer_' . trim(preg_replace('/.html|.tpl$|[^\w+]/', '_', $fileName), '_');
    }

    /**
     * @param array $fileContents
     * @param bool $parseFiles
     * @param array $excludedSource
     * @return void
     */
    private function parseFileContents(array $fileContents, bool $parseFiles, array $excludedSource): void
    {
        $sanitize = [
            '{{' => '\u007b',
            '}}' => '\u007d',
        ];

        foreach ($fileContents as $fileName => $data) {
            $this->lastLine = 0;
            $this->snippetInSourceOccurrences = [];

            $data['content'] = strtr($data['content'], $sanitize);

            $content = $data['content'];
            $checksum = $data['checksum'];

            $className = $this->createClassNameFromFileName($fileName);
            $cacheFilePath = $this->cacheDirectory . $className . '_' . $checksum . '.php';

            /**
             * Loading all files with name /path/renderer_index_*.php
             */
            $files = $this->getFile($className);

            /**
             * validating checksum, if file has been changed than filename can\'t be equal to older one
             */
            if (!in_array($cacheFilePath, $files) || $parseFiles === true) {

                /**
                 * removing all old generated files
                 */
                foreach ($files as $file) {
                    if (file_exists($file))
                        unlink($file);
                }

                /**
                 * Searching for all occurrences of HTML blocks
                 * {content}
                 *      <div>
                 *      </div>
                 * {/content}
                 */

                preg_match_all('/(?<!\S)\{(\w+)}(.*?)\{\/\1}/s', $content, $blocks, PREG_SET_ORDER);

                $fn = [];
                foreach ($blocks as $block) {
                    /**
                     * $block[1] is the name of the block {layout}html content{/app}
                     * $block[2] is 'html content'
                     */
                    $fn[$block[1]][] = $block[2];
                }

                $method = [];

                $this->reset = [];
                foreach ($fn as $name => $contents) {
                    $method[] = $this->createNewFunction($content, $name, $contents);
                }

                $constructor = "public function fileName():string \n{\n\treturn '" . $fileName . "';\n}\n public function init(array \$params = [], array \$lang = []): void \n{\n\t\$this->lang = \$lang;\n\n\t\$this->val = \$params;\n" . implode("\n", $this->reset) . "\n\n}";
                $methods = implode("\n", $method);

                preg_match_all('/public function render_(.*)/', $methods, $allMethodNames, PREG_PATTERN_ORDER);

                $interfaceMethods = [];
                $interfaceMethods[] = 'public function fileName():string;';
                $interfaceMethods[] = 'public function init(array $params = [], array $lang = []): void;';
                if (!empty($allMethodNames[0])) {
                    foreach ($allMethodNames[0] as $methodName) {
                        $interfaceMethods[] = trim($methodName) . ';';
                    }
                }

                $interfaceName = "interface_" . $className;
                $interface = "interface $interfaceName {\n\t" . implode("\n\t", $interfaceMethods) . "\n}";

                $fn = "<?php\n/*filename:" . $fileName . "*/\nnamespace Nlg\Aurora;\n$interface\nclass $className extends Loader implements $interfaceName {\n\n$constructor\n\n" . $methods . "}";

                /**
                 * Removing empty lines from source code
                 */
                $fn = preg_replace("/^[ \t]*\r?\n/m", "", $fn);

                $excludedSource['\u007b'] = '{';
                $excludedSource['\u007d'] = '}';

                $fn = strtr($fn, $excludedSource);

                file_put_contents($cacheFilePath, $fn);
            }
        }
    }

    /**
     * @param string $file
     * @return array
     */
    private function getFile(string $file): array
    {
        return glob($this->cacheDirectory . $file . '_*.php');
    }

    /**
     * @param array $const
     * @return void
     */
    public function setLanguageConstants(array $const = []): void
    {
        $this->languageConstants = $const;
    }

    /**
     * @param array $routes
     * @return void
     */
    public function setRoutes(array $routes = []): void
    {
        if (isset($routes['*'])) {
            foreach ($routes as $route => $templates) {
                if ($route === '*') continue;

                foreach ($templates as $value) {
                    if (preg_match("/\*\.(\S+)$/", $value)) {

                        $files = glob($this->workingDirectory . $value);
                        $templates = [];
                        foreach($files as $file) {
                            $templates[] = str_replace($this->workingDirectory, '', $file);
                        }
                    }
                }

                $routes[$route] = array_values(array_unique(array_merge($routes['*'], $templates)));
            }
        }

        $this->routes = $routes;
    }

    /**
     * @param array $files
     * @return void
     */
    public function setFiles(array $files = []): void
    {
        $this->workingFiles = $files;
    }

    /**
     * @param array $params
     * @return void
     */
    public function setVariables(array $params): void
    {
        if (empty($params['domain'])) {
            $params['domain'] = $this->getDomain();
        }

        $this->templateVariables = $params;
    }

    /**
     * @param string $route
     * @return void
     */
    public function setWorkingUrl(string $route): void
    {
        $this->workingUrl = $route;
    }

    /**
     * @return string
     */
    private function getWorkingUrl(): string
    {
        if (empty($this->workingUrl)) {

            $uri = $_SERVER['REQUEST_URI'];
            $path = parse_url($uri, PHP_URL_PATH) ?? '/';
            $segments = explode('/', trim($path, '/'));

            return !empty($segments[0]) ? $segments[0] : "";

        } else {

            return $this->workingUrl;

        }
    }

    /**
     * @return string
     */
    public function getDomain(): string
    {
        $scheme = 'http';
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            $scheme = 'https';
        } elseif (isset($_SERVER['REQUEST_SCHEME'])) {
            $scheme = $_SERVER['REQUEST_SCHEME'];
        }

        $host = $_SERVER['HTTP_HOST'];

        return $scheme . '://' . $host . '/';
    }

    /**
     * @return array
     */
    private function gettingWorkingFiles(): array
    {
        if (empty($this->workingFiles) && isset($this->workingUrl)) {

            return $this->routes[$this->workingUrl] ?? [];

        } else if (!empty($this->workingFiles)) {

            return $this->workingFiles;

        } else {

            $this->workingUrl = $this->getWorkingUrl();

            $this->workingFiles = $this->routes[$this->workingUrl] ?? [];

            return $this->workingFiles;
        }
    }

    /**
     * @param string $startingBlock
     * @return string
     */
    public function render(string $startingBlock): string
    {

        $html = '';
        $this->workingFiles = $this->gettingWorkingFiles();

        $notExists = [];
        $cacheCreated = false;
        try {
            foreach ($this->workingFiles as $key => $file) {
                $fullPath = $this->workingDirectory . trim($file, "/");
                if (file_exists($fullPath)) {

                    $name = $this->createClassNameFromFileName($file);
                    $files = $this->getFile($name);
                    $className = 'Nlg\Aurora\\' . $name;

                    if (empty($files[0]) && $cacheCreated === false) {
                        $this->createCache(!$this->productionMode);

                        $files = $this->getFile($name);
                        $cacheCreated = true;
                    }

                    if (empty($files[0])) {

                        throw new Exception('Error handling templates: Template has not been found!');

                    } else {

                        require_once($files[0]);

                        $instance = new $className();
                        $instance->init($this->templateVariables ?? [], $this->languageConstants ?? []);
                        self::$templateInstances[] = $instance;

                    }
                }
            }

            $html = trim($this->renderBlock('render_' . $startingBlock, $this->templateVariables, false, true));

        } catch (Throwable $e) {

            if (!$this->productionMode) {

                $error = $e->getTrace()[0]['args'][0] ?? [];

                $file = $error['file'] ?? $e->getFile();
                $line = $error['line'] ?? $e->getLine();

                $this->catchingError($file, $line, $e->getMessage());

            } else {
                if ($e->getMessage() === 'Page Not Found') {
                    $this->errors(404, [
                        '404 | Page Not Found'
                    ]);
                } else {
                    $this->errors(500, [
                        '500 | Internal Server Error'
                    ]);
                }
            }
        }

        return $html;
    }

    /**
     * @param string $methodName
     * @param array $params
     * @param bool $passingByReference
     * @param bool $startingBlock
     * @return string
     * @throws Exception
     */
    protected function renderBlock(string $methodName, array &$params, $passingByReference = false, $startingBlock = false): string
    {
        $originalParameters = $params;
        $result = '';

        $blocks = [];

        $blockNotFound = true;

        foreach (self::$templateInstances as $instance) {
            if (method_exists($instance, $methodName)) {
                if (in_array($methodName, ['render_Input', 'render_Textarea', 'render_Select', 'render_Radio', 'render_Checkbox'])) {

                    foreach (['isHidden', 'chain', 'isInvalid', 'label', 'isRequired', 'isReadOnly', 'error', 'info', 'value', 'data', 'title', 'selected', 'name', 'checked', 'isChecked', 'ref', 'arial-label'] as $n) {
                        $instance->val[$n] = null;
                    }
                }

                $instance->val = array_merge($instance->val, $params);

                $blockNotFound = false;
                $result .= $instance->{$methodName}($params);

                if ($passingByReference) {

                    $params = array_merge($instance->val, $originalParameters);

                } else {

                    $params = $instance->val;

                }
            } else {

                $fileName = $instance->fileName();
                $blocks[$fileName][] = "<strong>" . $fileName . "</strong>";

                $methods = get_class_methods($instance);
                foreach ($methods as $method) {
                    if (str_starts_with($method, "render_")) {
                        $block = str_replace('render_', '', $method);
                        $blocks[$fileName][] = "<span>{" . $block . "}</span>";
                    }
                }
            }
        }

        if ($blockNotFound && $startingBlock === true) {
            if ($this->productionMode) {

                throw new Exception('Page Not Found');

            } else {
                $methodName = str_replace('render_', '', $methodName);
                $err = [];
                $err[] = 'Input block not found: <strong>' . $methodName . '</strong>';
                $err[] = '<br /><pre>print $templateEngine->render("' . $methodName . '");</pre>';
                if (!empty($blocks)) {
                    $err[] = '<strong>Loaded templates: </strong>';

                    $err[] = '<pre id="tpl">';
                    foreach ($blocks as $block) {
                        $err[] = implode("\n", $block);
                    }
                }
                $err[] = '</pre>';

                $this->errors(500, $err ?? []);
            }
        }

        return $result;
    }
}