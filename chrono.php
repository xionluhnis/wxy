<?php

class TimingProfiler {

    private $diffs;
    private $names;
    private $length;

    public function __construct() {
        $this->diffs = array();
        $this->names = array();
    }

    public function mark($name = 'unknown', $prev) {
        $time = microtime(TRUE);
        $this->diffs[] = $time - $prev;
        $this->names[] = $name;
        $this->length += 1;
        return $time;
    }

    public function start() {
        return $this->mark('start', 0);
    }

    public function output() {
        for($i = 1; $i < $this->length; ++$i){
            echo '#' . $i . ' ' . $this->names[$i] . ': ' . $this->diffs[$i] . "sec\n";
        }
    }

}

class Chrono {

    private $prefix;
    private $profiler;
    private $last;

    public function __construct($pre, $pro) {
        $this->prefix = $pre;
        $this->profiler = $pro;
    }

    public function tic($prev) {
        $this->last = $prev;
        return $this;
    }

    public function toc($name) {
        $this->last = $this->profiler->mark($this->prefix . $name, $this->last);
        return $this;
    }

    public function last() {
        return $this->last;
    }

}

global $profiler;
global $profiler_chrono;

function tic(){
    global $profiler;
    global $profiler_chrono;
    $profiler = new TimingProfiler();
    $profiler_chrono = new Chrono('', $profiler);
    return $profiler_chrono->tic(0)->toc('start');
}

function toc($name){
    global $profiler;
    global $profiler_chrono;
    if(!$profiler)
        tic();
    $profiler_chrono->toc($name);
}

function tictoc($prefix){
    global $profiler;
    global $profiler_chrono;
    $chrono = new Chrono($prefix, $profiler);
    $chrono->tic($profiler_chrono->last());
    return $chrono;
}

function time_profile() {
    global $profiler;
    if($profiler)
        $profiler->output();
}

?>
