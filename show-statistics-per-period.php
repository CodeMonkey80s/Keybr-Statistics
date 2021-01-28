<?php

/**
 * MIT License
 *
 * Copyright (c) 2019, 2020 Michal Przybylowicz
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

/**
 * Max value of the graph in characters
 */
$graphMaxValue = 110;

/**
 * Minimum console width for graph to fit
 */
$consoleMinValue = $graphMaxValue + 23;

/**
 * Calculates statistics for given date periond (day/week/month/year)
 *
 * @param $samples
 * @param $date
 * @return array
 */
function calculateStatistics($samples, $date)
{
    $statistics = [
        "date"              => $date,
        "number_of_samples" => count($samples),
        "sum_cpm"           => array_sum(array_column($samples, "speed")),
        "average_cpm"       => 0,
        "average_wpm"       => 0,
        "median_wpm"        => 0,
        "mode_wpm"          => 0,
        "average_errors"    => array_sum(array_column($samples, "errors")),
    ];

    sort($samples);

    $statistics["average_errors"] = number_format($statistics["average_errors"] / $statistics["number_of_samples"], 2);
    $statistics["average_cpm"] = number_format($statistics["sum_cpm"] / $statistics["number_of_samples"], 2);
    $statistics["average_wpm"] = number_format($statistics["average_cpm"] / 5, 2);
    $statistics["median_wpm"] = number_format($samples[$statistics["number_of_samples"] / 2]["speed"] / 5, 2);

    $values = array_count_values(array_column($samples, "speed"));
    $statistics["mode_wpm"] = number_format(array_search(max($values), $values) / 5, 2);

    return $statistics;
}

/**
 * Prints nicely formatted results
 *
 * @param $statistics
 * @param $date
 */
function showResults($statistics)
{
    echo "--------------------------------\n";
    echo "Date            :  {$statistics['date']}\n";
    echo "Samples         :  {$statistics['number_of_samples']}\n";
    echo "Average (cpm)   :  {$statistics['average_cpm']}\n";
    echo "Average (wpm)   :  {$statistics['average_wpm']}\n";
    echo "Median  (wpm)   :  {$statistics['median_wpm']}\n";
    echo "Mode    (wpm)   :  {$statistics['mode_wpm']}\n";
    echo "Average Errors  :  {$statistics['average_errors']}\n";
    echo "\n";
}

function showHelp()
{
    echo "\n";
    echo "Example Usage: \n";
    echo "\n";
    echo "$ php " . basename(__FILE__) . " [--option=value]\n";
    echo "\n";
    echo "--filename=[filename]             - JSON file downloaded from keybr.com/profile\n";
    echo "--colors=1                        - Show colored Graph\n";
    echo "--graph=1                         - Show Graph\n";
    echo "--list=1                          - Show List with results\n";
    echo "--lessontype=auto                 - Count only selected lesson type\n";
    echo "--period=[day/week/month/year]    - Calculate only for selected period\n";
    echo "--date-after=Y/m/d                - Show only after this date\n";
    echo "--minwpm=80                       - Show only for wpm more than value\n";
    echo "--csv=1                           - Output data to csv file\n";
    echo "\n";
}

function parseCommandLineArguments()
{
    global $argv;
    $arguments = [];
    for ($i = 0; $i < count($argv); $i++) {
        if (preg_match('/^--([^=]+)=(.*)/', $argv[$i], $match)) {
            $arguments[$match[1]] = $match[2];
        }
    }

    return $arguments;
}

function run()
{
    global $consoleMinValue;
    global $graphMaxValue;

    $arguments = parseCommandLineArguments();

    $useColors = isset($arguments["colors"]) ? (bool)($arguments["colors"]) : false;
    $showHelp = isset($arguments["help"]) ? true : false;
    $showList = isset($arguments["list"]) ? (bool)($arguments["list"]) : false;
    $showGraph = isset($arguments["graph"]) ? (bool)($arguments["graph"]) : false;
    $minWPM = isset($arguments["minwpm"]) ? intval($arguments["minwpm"]) : false;
    $lessonType = isset($arguments["lessontype"]) ? $arguments["lessontype"] : "auto";
    $period = isset($arguments["period"]) ? $arguments["period"] : "month";
    $dateAfter = isset($arguments["date-after"]) ? $arguments["date-after"] : false;
    $createCSV = isset($arguments["csv"]) ? $arguments["csv"] : false;

    if ($showHelp) {
        showHelp();
        exit;
    }

    $file = isset($arguments["file"]) ? $arguments["file"] : false;
    if ($file===false) {
        echo "ERROR: Provide valid json file downloaded from keybr.com/profile !\n";
        showHelp();
        exit;
    }

    $path = realpath($file);
    if (file_exists($path)===false) {
        echo "ERROR: File does not exits !\n";
        exit;
    }

    $json = file_get_contents($path);
    $json = json_decode($json);
    if ($json===false) {
        echo "ERROR: Invalid json file !\n";
        exit;
    }

    echo "\n";

    $statisticsPerPeriod = [];

    $finalAverageWPM = 0;
    $finalAverageErrors = 0;
    $finalAccuracy = 0;
    $numberOfSamples = 0;
    $samples = [];
    $currentDate = false;
    foreach ($json as $entry) {
        $timestamp = substr($entry->timeStamp, 0, 10);
        $datetime = DateTime::createFromFormat("Y-m-d", $timestamp);

        if ($dateAfter) {
            $datetime2 = DateTime::createFromFormat("Y/m/d", $dateAfter);
            if ($datetime < $datetime2) continue;
        }
        
        if ($period=="year") {
            $date = $datetime->format("Y");
        } elseif ($period=="month") {
            $date = $datetime->format("Y/m");
        } elseif ($period=="week") {
            $weekNum = str_pad($datetime->format("W"), 3, "0", STR_PAD_LEFT);
            $date = $datetime->format("Y") . "/" . $weekNum;
        } elseif ($period=="day") {
            $date = $datetime->format("Y/m/d");
        }

        if ($currentDate==false) {
            $currentDate = $date;
        }

        $finalAverageWPM += $entry->speed;
        $finalAverageErrors += $entry->errors;
        $numberOfSamples++;

        if ($entry->lessonType==$lessonType) {
            $samples[$currentDate][] = [
                "speed"  => $entry->speed,
                "errors" => $entry->errors,
            ];
        }
        
        if ($currentDate!=false && $date!=$currentDate) {
            $currentDate = $date;
        }
    }

    $finalAverageWPM = number_format($finalAverageWPM / $numberOfSamples / 5, 2);
    $finalAverageErrors = number_format($finalAverageErrors / $numberOfSamples, 2);
    
    if (count($samples) > 0) {
        foreach ($samples as $date => $sample) {
            $statistics = calculateStatistics($sample, $date);
            $statisticsPerPeriod[] = $statistics;
        }
    }

    if ($showList) {
        foreach ($statisticsPerPeriod as $statistics) {
            showResults($statistics);
        }
    }

    if ($createCSV) {
        $filenameCSV = "./stats.csv";
        unlink($filenameCSV);
        $line = "";
        foreach ($statisticsPerPeriod as $statistics) {
            $line .= number_format($statistics["average_cpm"] / 5, 0) . ",";
        }
        $line .= "\n";
        file_put_contents($filenameCSV, $line);
    }

    if ($showGraph) {
        $width = exec('tput cols');
        if ($width >= $consoleMinValue) {
            //echo "Console Width: {$width} (Displaying 'Mode WPM'), Max Value: {$graphMaxValue}\n\n";
            echo "Console Width: {$width} (Displaying 'Average WPM'), Max Value: {$graphMaxValue}\n\n";
            if (count($statisticsPerPeriod) > 0) {
                foreach ($statisticsPerPeriod as $data) {
                    if ($minWPM==false || $data["average_wpm"] >= $minWPM) {
                        $date = $data["date"];
                        $wpm = $data["average_wpm"];
                        $errors = $data["average_errors"];
                        $blocks = round($wpm, 0, PHP_ROUND_HALF_UP);
                        if ($useColors) {
                            echo "\e[37m{$date} \e[90m:\e[0m ";
                        } else {
                            echo "{$date} : ";
                        }
                        for ($i = 1; $i <= $graphMaxValue; $i++) {
                            if ($i < $blocks) {
                                if ($useColors) {
                                    echo "\e[92m#\e[0m";
                                } else {
                                    echo "#";
                                }
                            } else {
                                if ($useColors) {
                                    echo "\e[90m-\e[0m";
                                } else {
                                    echo "-";
                                }
                            }
                        }
                        $blocks = str_pad($blocks, 3, " ", STR_PAD_LEFT);
                        if ($useColors) {
                            echo " {$blocks}  \e[91m{$errors}\e[0m\n";
                        } else {
                            echo " {$blocks}  {$errors}\n";
                        }
                    }
                }
            }
            echo "\n";
            echo "     Speed : {$finalAverageWPM} WPM (Average)\n";
            echo "    Errors : \e[91m{$finalAverageErrors}\e[0m\n";
            //echo "  Accuracy : {$finalAccuracy} %\n";
        } else {
            echo "Console width ({$width}) is too small. Should be at least ({$consoleMinValue}). Graph is not displayed.\n";
        }
    }
    echo "\n";
}

run();

?>
