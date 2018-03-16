<?php
/**
 * Created by PhpStorm.
 * User: jimmyc
 * Date: 23/05/2017
 * Time: 16:10
 */

namespace AppBundle\Storage;

use AppBundle\Entity\Device;
use AppBundle\Entity\Probe;
use AppBundle\Entity\SlaveGroup;
use AppBundle\Exception\RrdException;
use AppBundle\Exception\WrongTimestampRrdException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class RrdStorage extends Storage
{
    private $logger = null;
    private $path = null;

    private $predictions = array(
        array(
            'function' => 'HWPREDICT',
            'rows' => 51840,
            'alpha' => 0.1,
            'beta' => 0.0035,
            'period' => 1440
        ),
    );

    public function __construct($path, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->path = $path;

        if (!file_exists($this->path)) {
            mkdir($this->path);
        }
    }

    public function getFilePath(Device $device, Probe $probe, SlaveGroup $group)
    {
        $path = $this->path.$device->getId();

        if (!file_exists($path)) {
            mkdir($path);
        }

        $path = $this->path.$device->getId()."/".$probe->getId();

        if (!file_exists($path)) {
            mkdir($path);
        }

        return $this->path.$device->getId()."/".$probe->getId()."/".$group->getId().'.rrd';
    }

    public function store(Device $device, Probe $probe, SlaveGroup $group, $timestamp, $data)
    {
        $path = $this->getFilePath($device, $probe, $group);

        if (!file_exists($path)) {
            $this->create($path, $probe, $timestamp, $data);
        }
        $this->update($path, $probe, $timestamp, $data);
    }

    private function create($filename, Probe $probe, $timestamp, $data)
    {
        $start = $timestamp - 1;

        $options = array(
            "--start", $start,
            "--step", $probe->getStep()
        );
        foreach ($data as $key => $value) {
            $options[] = sprintf(
                "DS:%s:%s:%s:%s:%s",
                $key,
                'GAUGE',
                $probe->getStep() * 2,
                0,
                "U"
            );
        }

        foreach ($probe->getArchives() as $archive) {
            $options[] = sprintf(
                "RRA:%s:0.5:%s:%s",
                strtoupper($archive->getFunction()),
                $archive->getSteps(),
                $archive->getRows()
            );
        }

        foreach ($this->predictions as $value) {
            $options[] = sprintf(
                "RRA:%s:%s:%s:%s:%s",
                strtoupper($value['function']),
                $value['rows'],
                $value['alpha'],
                $value['beta'],
                $value['period']
            );
        }


        $return = rrd_create($filename, $options);
        if (!$return) {
            throw new RrdException(rrd_error());
        }
    }

    private function update($filename, $probe, $timestamp, $data)
    {
        $info = rrd_info($filename);
        $update = rrd_lastupdate($filename);

        if ($info['step'] != $probe->getStep()) {
            throw new RrdException("Steps are not equal, ".$probe->getStep()." is configured, RRD file is using ".$info['step']);
        }

        if ($update["last_update"] >= $timestamp) {
            throw new WrongTimestampRrdException("RRD $filename last update was ".$update["last_update"].", cannot update at ".$timestamp);
        }

        $template = array();
        $values = array($timestamp);

        foreach($data as $key => $value) {
            $template[] = $key;
            $values[] = $value;
        }
        $options = array("-t", implode(":", $template), implode(":", $values));

        $return = rrd_update($filename, $options);

        $this->logger->debug("Updating $filename with ".print_r($options, true));

        if (!$return) {
            throw new RrdException(rrd_error());
        }
    }

    public function fetch(Device $device, Probe $probe, SlaveGroup $group, $timestamp, $key, $function)
    {
        $path = $this->getFilePath($device, $probe, $group);

        $result = rrd_fetch($path, array($function, "--start", $timestamp - $probe->getStep()));

        if (!$result) {
            return null;
        }

        return reset($result['data'][$key]);
    }

    public function validate(Device $device, Probe $probe, SlaveGroup $group)
    {
        $filename = $this->getFilePath($device, $probe, $group);

        $finder = new ExecutableFinder();
        if (!$rrdtool = $finder->find("rrdtool")) {
            throw new \Exception("rrdtool is not installed on this system.");
        }

        $info = rrd_info($filename);
        if ($info['step'] != $probe->getStep()) {
            $this->logger->info("Running rrdtune to change step from ".$info['step']." to ".$probe->getStep());
        }

        $rra = $this->readArchives($filename);

        //add new rra's
        foreach ($probe->getArchives() as $archive) {
            $found = false;
            foreach($rra as $key => $item) {
                if ($item['cf'] == $archive->getFunction() && $item['rows'] == $archive->getRows() && $item['pdp_per_row'] == $archive->getSteps()) {
                    $found = true;
                    unset($rra[$key]);
                }
            }
            if (!$found) {
                $this->logger->info("Adding $archive");
                $rradef = sprintf(
                    "RRA:%s:0.5:%s:%s",
                    strtoupper($archive->getFunction()),
                    $archive->getSteps(),
                    $archive->getRows()
                );
                $process = new Process("rrdtool tune $filename $rradef");
                $process->run();
            }
        }

        $rra = $this->readArchives($filename);

        //delete obsolete rra's
        for($i = count($rra) - 1; $i >=0; $i--) {
            $item = $rra[$i];
            $found = false;
            foreach ($probe->getArchives() as $archive) {
                if ($item['cf'] == $archive->getFunction() && $item['rows'] == $archive->getRows() && $item['pdp_per_row'] == $archive->getSteps()) {
                    $found = true;
                }
            }
            if (!$found && in_array($item['cf'], array("AVERAGE", "MIN", "MAX"))) {
                $this->logger->info("Removing #$i " . $item['cf'] . "-" . $item['pdp_per_row'] . "-" . $item['rows']);
                $process = new Process("rrdtool tune $filename DELRRA:$i");
                $process->run();
            }
        }
    }

    private function readArchives($filename)
    {
        $info = rrd_info($filename);

        $rra = array();
        foreach ($info as $key => $item) {
            if (substr($key, 0, 3) == "rra") {
                preg_match("/rra\[([\d]+)\]\.([\w\_]+)/", $key, $matches);
                $rra[$matches[1]][$matches[2]] = $item;
            }
        }

        return $rra;
    }
}