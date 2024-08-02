<?php

include ("jpgraph-2.1.1/src/jpgraph.php");
include ("jpgraph-2.1.1/src/jpgraph_line.php");
include ("jpgraph-2.1.1/src/jpgraph_regstat.php");

/**
 * GrowthChart class
 *
 * @author Jonathan Abbett <jonathan.abbett@childrens.harvard.edu>
 * @version 1.1
 * @copyright Jonathan Abbett and Children's Hospital Informatics Program, 2007
 *
 */
class GrowthChart
{
        /**
         * Male sex
         *
         */
        const SEX_MALE                  = 1;
        /**
         * Female sex
         *
         */
        const SEX_FEMALE                = 2;

        private $style;
        private $title;
        private $sex;
        private $maxAgeMonths;
        private $patientXarray;
        private $patientYarray;
        private $width;
        private $height;
        /**
         * Constructor, used to initialize necessary variables.
         *
         * @param string $style Chart style, from available dataset filenames, i.e. bmi-age, weight-length
         * @param string $title Title for the graph, provided by M program
         * @param integer $sex Patient sex, from available SEX constants
         * @param decimal $maxAgeMonths The greatest patient age used in the chart, used to decide whether chart is infant (0-36 mo.) or regular (2-20 yrs.)
         * @param integer $width Width of chart in pixels
         * @param integer $height Height of chart in pixels
         * @param array $patientXarray Array of decimals for patient X data (i.e. age in months)
         * @param array $patientYarray Array of decimals for patient Y data (i.e. length, height, BMI, etc.)
         * @return GrowthChart
         */
        public function GrowthChart($style, $title = null, $sex, $maxAgeMonths, $width = 800, $height = 800, $patientXarray = null, $patientYarray = null)
        {
                $this->style = $style;
                $this->title = $title;
                $this->sex = $sex;
                $this->maxAgeMonths = $maxAgeMonths;
                $this->width = $width;
                $this->height = $height;
                $this->patientXarray = $patientXarray;
                $this->patientYarray = $patientYarray;
        }

        private static function generateSourceXData($min, $max) {

                $data = array();

                $data[] = $min;

                for ($i = $min + 0.5; $i < $max; $i++) {
                        $data[] = $i;
                }

                $data[] = $max;

                return $data;

        }

        /**
         * Renders the chart, outputting a PNG image.
         *
         */
        public function render()
        {

                // Create and set-up the graph
                $g  = new Graph($this->width, $this->height, "auto");
                $g->SetColor('white');
                $g->SetFrame(false);
                $g->SetMargin(25,20,20,25);
                $g->SetMarginColor('white');
                // Load data from XML

                if ($this->sex == GrowthChart::SEX_MALE) {
                        $this->style .= '-male';
                } else {
                        $this->style .= '-female';
                }

                if ($this->maxAgeMonths <= 36) {
                        $this->style .= '-infant';
                }

                $xml = simplexml_load_file("data/$this->style.xml");

                $xdata = GrowthChart::generateSourceXData((float)$xml->sourceXStart, (float)$xml->sourceXEnd);

                $g->SetScale("linlin", (float)$xml->yMin, (float)$xml->yMax, (float)$xml->xMin, (float)$xml->xMax);
                if ((float)$xml->ticksMajor != 0) {
                        $g->yscale->ticks->Set((float)$xml->ticksMajor, (float)$xml->ticksMinor);
                }
                $g->xaxis->SetLabelFormat('%1.1f');
                $g->xaxis->SetFont(FF_TREBUCHE, FS_NORMAL, 9);
                $g->xgrid->Show(true);
                $g->yaxis->HideZeroLabel();
                $g->yaxis->SetFont(FF_TREBUCHE, FS_NORMAL, 9);
                $g->ygrid->SetFill(true,'#EFEFEF@0.5','#FFFFFF@0.5');
                if (!empty($this->title))
                {
                $g->title->Set($this->title);
                $g->title->SetColor("red");
                $g->title->SetFont( FF_FONT2, FS_BOLD);
                }
                $xml = simplexml_load_file("data/$this->style.xml");


                foreach ($xml->percentile as $p) {

                        $percentile = $p->label;
                        $yp = array();

                        foreach ($p->value as $value) {
                                $yp[] = (float)$value;
                        }

                        // Create the spline
                        $spline = new Spline($xdata, $yp);

                        // Get smoothed points
                        list($newx, $newy) = $spline->Get(100);

                        $lplot = new LinePlot($newy, $newx);
                        $lplot->SetColor('#CCCCCC');

                        if ($percentile == '50')
                        {
                                $lplot->SetColor('#666666');
                        }

                        // Add the plots to the graph and stroke
                        $g->Add($lplot);

                        // Add percentile label to graph
                        $txt = new Text($percentile . ($percentile == '3' ? 'rd' : 'th'));
                        $txt->SetScalePos($xdata[sizeof($xdata)-1]+(float)$xml->percentileXNudge,$yp[sizeof($yp)-1]+(float)$xml->percentileYNudge);
                        $txt->SetColor('#666666');
                        $txt->SetFont(FF_TREBUCHE, FS_NORMAL, 9);
                        $g->AddText($txt);
                }

                if (!empty($this->patientXarray) && !empty($this->patientYarray) && sizeof($this->patientXarray) == sizeof($this->patientYarray))
                {
                        $patientPlot = new LinePlot($this->patientYarray, $this->patientXarray);
                        $patientPlot->SetColor('orange');
                        $patientPlot->SetWeight(3);
                        $patientPlot->value->Show();
                        $patientPlot->value->SetColor('brown');
                        $patientPlot->value->SetFont(FF_COURIER, FS_BOLD);
                        $patientPlot->value->SetAlign('left', 'top');
                        $patientPlot->value->SetMargin(-5);
                        $patientPlot->mark->SetType(MARK_DIAMOND);
                        $patientPlot->mark->SetWidth(7);
                        $patientPlot->mark->SetColor('orange');
                        $patientPlot->mark->SetFillColor('red');

                        $g->Add($patientPlot);
                }

                $g->Stroke();
        }
}

?>