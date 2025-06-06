<?php
// last changes: 26.04.2005

require('midi.class.php');

/****************************************************************************
Software: MidiRttl Class
Version:  0.3
Date:     2005/07/12
Author:   Valentin Schmidt
Contact:  fluxus@freenet.de
License:  Freeware

Extends Midi Class to support the RTTL ringtone format
****************************************************************************/

class MidiRttl extends Midi {

var $notes = array('c','c#','d','d#','e','f','f#','g','g#','a','a#','b');
var $defaultDur;
var $defaultScale;
var $defaultBpm;

/****************************************************************************
*                                                                           *
*                              Public methods                               *
*                                                                           *
****************************************************************************/

//---------------------------------------------------------------
// CONSTRUCTOR
//---------------------------------------------------------------
function MidiRttl($defaultDur=4, $defaultScale=5, $defaultBpm=63){
	$this->defaultDur = $defaultDur;
	$this->defaultScale = $defaultScale;
	$this->defaultBpm = $defaultBpm;
}

//---------------------------------------------------------------
// returns RTTL string (MIDI2RTTL conversion)
// if $title is specified, this will be the RTTL name (max. 10 characters)
// if tracknumber $tn is specified, the corresponding track will be used
//---------------------------------------------------------------
function getRttl($title='',$tn=-1){
	
	if ($tn<0) $track = $this->_findFirstContentTrack();
	else $track = $this->getTrack($tn);
	$commands = array();
	$last = 0;
	$dt = 0;
	$cnt = count($track);
	for ($i=0;$i<$cnt;$i++){
		$line = $track[$i];
		$msg = explode(' ',$line);
		
		// try to get title from meta event
		if ($title==''&&$msg[1]=='Meta'&&$msg[2]=='TrkName') {
			$title=trim($msg[3]);
			if ($title{0}=='"') $title=substr($title, 1);
			if ($title{strlen($title)-1}=='"') $title=substr($title, 0, -1);
		}
			
		if ($msg[1]=='On' && $msg[4]!='v=0'){
			$time = $msg[0];
			
			$pause=$time-$last-$dt;
			if ($pause>0){
				list($dot, $quarters) = $this->_checkDotted($pause/$this->timebase);
				$dur = max(1,round(4 / $quarters));
				$commands[] = ($dur!=$this->defaultDur?$dur:'').'p'.$dot;
			}
			
			// find note duration
			$dt = 0;
			for ($j=$i+1;$j<$cnt;$j++){
				$msgNext = explode(' ',$track[$j]);
				if ($msgNext[1]=='On'||$msgNext[1]=='Off'){
					$dt = $msgNext[0] - $msg[0];
					break;
				}
			}
			
			eval("\$".$msg[3].';');
			$note = $this->notes[$n % 12];
			$scale = floor($n/12);
			
			if ($dt>0){
				list($dot, $quarters) = $this->_checkDotted($dt/$this->timebase);				
				$dur = max(1,round(4 / $quarters));
				//<duration> := "1" | "2" | "4" | "8" | "16" | "32" 
				$commands[] = ($dur!=$this->defaultDur?$dur:'').$note.$dot.($scale!=$this->defaultScale?$scale:'');
				$last = $time;
			}
		}

	}// for

	$title = ($title=='')?'mid2rttl':trim(substr($title, 0, 10));
	$rttl = "$title:d={$this->defaultDur},o={$this->defaultScale},b=".$this->getBpm().":" . implode(',', $commands);
	return $rttl;
}

//---------------------------------------------------------------
// import RTTL (RTTL2MIDI conversion)
//---------------------------------------------------------------
function importRttl($rttl, $instrument=0){	
	list($name,$controls,$tones) = explode(':', $rttl);
	$controls = explode(',', $controls);
	$tones = explode(',', $tones);
	
	foreach ($controls as $c) eval('$'.$c.';');
		
	$this->open();
	$this->type = 0;
	$this->timebase = 480;// ???
	$bpm = isset($b)?$b:$this->defaultBpm;
	$this->tempo = round(60000000/$bpm);
		
	$track = array();
	$track[] = '0 Meta TrkName "'.$name.'"';
	$track[] = '0 Tempo '.$this->tempo;
	$track[] = "0 PrCh ch=1 p=$instrument";
	
	$last = 0;
	$time = 0;
	foreach ($tones as $tone){
		preg_match ( '/^[0-9]*/', $tone, $test);
		$dur = $test[0];
		if ($dur == '') $dur = isset($d)?$d:$this->defaultDur;
		
		preg_match ( '/[a-p](\#*)/', $tone, $test);
		$note = $test[0];
		
		preg_match ( '/\./', $tone, $test);
		$dot = @$test[0];
		
		preg_match ( '/[0-9]*$/', $tone, $test);
		$scale = @$test[0];
		if ($scale=='') $scale = isset($o)?$o:$this->defaultScale;
		
		$quarters = 4 / $dur;
		$dt = $quarters * $this->timebase;
		if ($dot) $dt *= 1.5;

		if ($last) {
			$track[] = "$time Off ch=1 n=$last v=100";
			$last = 0;
		}
		if ($note!='p') {
			$note = 12 * $scale + array_search ( $note, $this->notes);
			$track[] = "$time On ch=1 n=$note v=100";
			$last = $note;
		}
		
		$time += $dt;
	} // foreach
	
	if ($last) $track[] = "$time Off ch=1 n=$last v=100";
	$track[] = "$time Meta TrkEnd";
	
	$this->tracks = array($track);
}


/****************************************************************************
*                                                                           *
*                              Private methods                              *
*                                                                           *
****************************************************************************/

//---------------------------------------------------------------
// finds first track containing note on events
//---------------------------------------------------------------
function _findFirstContentTrack(){
	if ($this->type==0) return $this->tracks[0];
	else {
		foreach ($this->tracks as $track)
			foreach ($track as $line){
				list(,$event) = explode(' ',$line);
				if ($event=='On') return $track;
			}
	}
	return false;
}

//---------------------------------------------------------------
// handles dotted notes
//---------------------------------------------------------------
function _checkDotted($quarters){
	$dotted = array(6, 3, 3/2, 3/4, 3/8, 3/16);
	foreach ($dotted as $test)
		// to avoid rounding errors check for +/- 10%
		if (abs($quarters/$test-1)<0.1)   //($this->_compare($quarters,$test))
			return array('.', $quarters*2/3);
	return array('', $quarters);
}
	
} // END OF CLASS
?>