#!/usr/bin/php
<?php 
/**
 * @version	$Id: atravelbookexport.php 1.0$
 * @copyright	Copyright (C) 2011 Kai Berggold
 * @license	GNU General Public License version 2 or later; 

 * Little Script to export the travelbook-data from sygic aura navigation app (Only Tested on android)
 * Usage: Export the log-files from the folder aura/res/travelbook, and then apply script e.g.:
 * atravelbookexport.php -v1 -tk filename.log 
 * Export can be done as tab seperated values (-tt), a short tab with only one row (-ts) and as kml-File (-tk)
 * Tested on version 11.2.3 (use -v2) and an the version present at June 2011 (use -v1)
 */

class geodata
{
	public $lattitude=0;
	public $longitude=0;
	public $timestamp=0;
	public $height=0;
}


class auraconvert 
{
	protected $geoTable=array();
	protected $startTime;
	protected $endTime;
	protected $timeSpan;
	protected $lengthStartAddress;
	protected $lengthEndAddress;
	protected $startAddress;
	protected $endAddress;
	protected $count;
	protected $minHeight;
	protected $maxHeight;
	protected $distance;
	protected $fileName;
	protected $data;



	public function __construct($options)
	{
		$this->UTCOffset=3600;
		if (isset($options["v"]) )
			$version=$options["v"];

		switch ($version) 
		{
			case 1:
			default:
				$this->offsetStartTime=9;
				$this->offsetDistance=17;
				$this->offsetStartAddress=25;
				$this->tableWidth=21;
				$this->timeShift=978303562;
				
			break;
			case 2:
				$this->offsetStartTime=9;
				$this->offsetDistance=17;
				$this->offsetStartAddress=25;
				$this->tableWidth=25;
				$this->timeShift=978303562;
				
			break;

			
		}
		
		

	}


	protected function getword($offset)
	{
		if (ord($this->data[$offset+3])<128)
			return(ord($this->data[$offset])+ord($this->data[$offset+1])*pow(2,8)+ord($this->data[$offset+2])*pow(2,16)+ord($this->data[$offset+3])*pow(2,24));
		else return  -(pow(2,32)- ((ord($this->data[$offset])+ord($this->data[$offset+1])*pow(2,8)+ord($this->data[$offset+2])*pow(2,16)+ord($this->data[$offset+3])*pow(2,24))-1));

	}
	
	protected function remove ($string,$remove)
	{
		if( $remove) 
		{
			$explode=explode(" ",$string);
			$sub=array();
			for($i=0;$i<$remove;$i++) 
			{
				$sub[]=$explode[count($explode)-1-$i];
			}
			
			return implode(" ",array_reverse($sub));
		}
		else 
		{
			return $string;
		}
		
	}
	protected function getLengthStartAddress()
	{
		if (!isset($this->lengthStartAddress))
			$this->lengthStartAddress=ord($this->data[$this->offsetStartAddress]);
		return $this->lengthStartAddress;
	}

	protected function getLengthEndAddress()
	{
		if (!isset($this->lengthEndAddress))
			$this->lengthEndAddress=ord($this->data[$this->offsetStartAddress+$this->getlengthStartAddress()*2+2]);
		return $this->lengthEndAddress;
	}


	public function getStartAddress()
	{	
		if (!isset($this->startAddress))
		{
			$data=$this->data;
			$this->startAddress="";
			for($i=$this->offsetStartAddress+2;$i<$this->offsetStartAddress+$this->getLengthStartAddress()*2+2;$i+=2)
				$this->startAddress=$this->startAddress.$data[$i];
		}
		return $this->startAddress;
	}

	public function getEndAddress()
	{
		if (!isset($this->endAddress))
		{
			$data=$this->data;
			$lengthStartAddress=$this->getLengthStartAddress();
			$lengthEndAddress=$this->getLengthEndAddress();
			$this->endAddress="";
			for($i=$this->offsetStartAddress+$lengthStartAddress*2+4;$i<$this->offsetStartAddress+$lengthStartAddress*2+4+$lengthEndAddress*2;$i+=2)
				$this->endAddress=$this->endAddress.$data[$i];	
		}
		return $this->endAddress;
	}


	public function getStartTime()
	{
		if (!isset($this->startTime))
		{
			$this->startTime=$this->getword($this->offsetStartTime)+$this->timeShift;
		}
		return $this->startTime;
		
	}

	public function getDistance()
	{
		if (!isset($this->distance))
		{
			$this->distance=$this->getword($this->offsetDistance)/1000;
		}
		return $this->distance;
		
	}

	public function getEndTime()
	{
		if (!isset($this->endTime))
		{
			$this->endTime=$this->getStartTime()+$this->getTimeSpan();
		}
		return $this->endTime;
		
	}

	public function getTimeSpan()
	{
	
		return $this->timeSpan;
	}

	public function getFileName()
	{
	
		return $this->fileName;
	}

	public function calcGeoTable()
	{
		$this->geoTable=array();
		$offset=4;
		$shiftTimeStamp=$this->getword($this->getCountOffset()+$offset+12);
		for($i=0;$i<$this->getCount();$i++) 
		{
			$newrow= new geodata;
			$newrow->longitude=($this->getword($this->getCountOffset()+$this->tableWidth*$i+$offset))/100000;
			$newrow->lattitude=($this->getword($this->getCountOffset()+$this->tableWidth*$i+$offset+4))/100000;
			$newrow->height=$this->getword($this->getCountOffset()+$this->tableWidth*$i+$offset+8);
			$newrow->timestamp=($this->getword($this->getCountOffset()+$this->tableWidth*$i+$offset+12)-$shiftTimeStamp)/1000;
			$this->geoTable[]=$newrow;
		}
		$this->timeSpan=($this->getword($this->getCountOffset()+$this->tableWidth*($i-1)+$offset+12)-$shiftTimeStamp)/1000;
	}

	public function getGeoTable()
	{
		return $this->geoTable;
	}

	public function getGeoTableLastRow()
	{
		$index=count($this->geoTable);
		return $this->geoTable[$index-1];
	}

	public function getGeoTableFirstRow()
	{
		return $this->geoTable[0];
	}


	public function getMinHeight()
	{
		$this->minHeight=10000;
		foreach($this->getGeoTable() as $key => $value) 
		{
			if ($value->height<$this->minHeight)
				$this->minHeight=$value->height;
		}
		return $this->minHeight;
	}

	public function getMaxHeight()
	{
		$this->maxHeight=-10000;
		foreach($this->getGeoTable() as $key => $value) 
		{
			if ($value->height>$this->maxHeight)
				$this->maxHeight=$value->height;
		}
		return $this->maxHeight;
	}


	public function readAuraFile($filename)
	{
		$this->data = file_get_contents($filename);
		$this->setFileName($filename);
		$this->calcGeoTable();
	}

	public function getCountOffset()
	{
		return $this->offsetStartAddress+$this->getLengthStartAddress()*2+4+$this->getLengthEndAddress()*2;
	}

	public function getCount()
	{
		if (!isset($this->count))
		{
			$lengthEndAddress=$this->getLengthEndAddress();
			$this->count=$this->getword($this->getCountOffset());
		}
		return $this->count;
		
	}


	public function setCount($count)	
	{
		$this->count=$count;
	}

	public function setLengthStartAddress($length)	
	{
		$this->lengthStartAddress=$length;
	}
	
	public function setLengthEndAddress($length)	
	{
		$this->lengthEndAddress=$length;
	}

	public function setStartAddress($address)	
	{
		$this->startAddress=$address;
	}

	public function setEndAddress($address)	
	{
		$this->endAddress=$address;
	}

	public function setStartTime($time)	
	{
		$this->startTime=$time;
	}

	public function setEndTime($time)	
	{
		$this->endTime=$time;
	}

	public function setTimeSpan($time)	
	{
		$this->timeSpan=$time;
	}

	public function setMinHeight($height)	
	{
		$this->minHeight=$height;
	}

	public function setMaxHeight($height)	
	{
		$this->maxHeight=$height;
	}

	public function setGeoTable($in)	
	{
		$this->geoTable=$in;
	}

	public function setFileName($fileName)	
	{
		$this->fileName=$fileName;
	}

	public function setDistance($distance)	
	{
		$this->distance=$distance;
	}

	public function tableOutput($options="")
	{
		//echo "Länge Startadresse:\t".$this->getLengthStartAddress()."\n";
		echo "Filename:\t".$this->getFileName()."\n";
		echo "Startadresse:\t".$this->getStartAddress()."\n";
		//echo "Länge Zieladresse:\t".$this->getLengthEndAddress()."\n";
		echo "Zieladresse:\t".$this->getEndAddress()."\n";
		echo "Distanz:\t".$this->getDistance()."\n";
		echo "Zahl der Zeilen:\t".$this->getCount()."\n";
		echo "Minimale Höhe:\t".$this->getMinHeight()."\n";
		echo "Maximale Höhe:\t".$this->getMaxHeight()."\n";
		echo "Startzeit:\t".date('d.m.Y H:i:s',$this->getStartTime())."\n";
		echo "Endzeit:\t".date('d.m.Y H:i:s',$this->getEndTime())."\n";
		echo "Dauer:\t".date('H:i:s',$this->getTimeSpan()-$this->UTCOffset)."\n";
		echo "Zeitstempel\tRelative Zeit (s)\tLÃ¤ngengrad\tBreitengrad\tHöhe (m)\n";
		foreach($this->getGeoTable() as $key => $value) 
		{
			echo date('d.m.Y H:i:s',$value->timestamp+$this->getStartTime())."\t".($value->timestamp)."\t". $value->lattitude."\t". $value->longitude."\t". $value->height."\n";
		}
		
	}

	public function tableHeaderOutput($tracks)
	{
		$i=1;
		echo "Zeile\tFilename\tStartadresse\tZieladresse\tDistanz\tZahl der Zeilen\tMinimale Höhe\tMaximale Höhe\tStartzeit\tEndzeit\tDauer\n";
		foreach($tracks as $track) 
		{
			echo $i."\t";
			echo $track->getFileName()."\t";
			echo $track->getStartAddress()."\t";
			echo $track->getEndAddress()."\t";
			echo $track->getDistance()."\t";
			echo $track->getCount()."\t";
			echo $track->getMinHeight()."\t";
			echo $track->getMaxHeight()."\t";
			echo date('d.m.Y H:i:s',$track->getStartTime())."\t";
			echo date('d.m.Y H:i:s',$track->getEndTime())."\t";
			echo date('H:i:s',$track->getTimeSpan()-$track->UTCOffset)."\n";
			$i++;
		}
	}



	public function kmlOutput($showStart,$showEnd,$color,$lineWidth,$remove)
	{
		
		echo '<?xml version="1.0" encoding="UTF-8"?>';
	?>
		<kml xmlns="http://www.opengis.net/kml/2.2">
		<Document>
		<name><?php echo  $this->getFileName();?></name>

		<description>
		Ort: <?php echo $this->remove($this->getStartAddress(),$remove);?> Zeit: <?php echo date('d.m.Y H:i:s', $this->getStartTime());?> Strecke: <?php echo  $this->getDistance()?> km
		</description>
		<Style id="lineStylea">
			<LineStyle>
				<color><?php echo $color?></color>
				<width><?php echo $lineWidth?></width>
			</LineStyle>
		</Style>
		<PolyStyle>
			<color><?php echo $color?></color>
		</PolyStyle>
		<?php if ($showStart):?>
		<Placemark>
			<name><?php echo $this->remove($this->getStartAddress(),$remove);?></name>
			<description>
				<![CDATA[
				<h1><?php echo  $this->remove($this->getStartAddress(),$remove);?></h1>
				<p><b>Zeit: </b><?php echo date('d.m.Y H:i:s', $this->getStartTime());?></br>
				<b>Strecke: </b><?php echo  $this->getDistance()?> km</br>
				</p>
				]]>
			</description>
			<Point> 
				<coordinates><?php $row=$this->getGeoTableFirstRow();echo $row->longitude.','.$row->lattitude.','.$row->height;?></coordinates>
			</Point>
		</Placemark>
		<?php endif;?>
		<Placemark>
		<name><?php echo $this->remove($this->getStartAddress(),$remove);?></name>
			<description>
				<![CDATA[
				<h1><?php echo $this->remove($this->getStartAddress(),$remove);?> nach <?php echo  $this->remove($this->getEndAddress(),$remove)?> </h1>
				<p>File: </b><?php echo  $this->getFileName()?></br>
				<b>Zeit: </b><?php echo date('d.m.Y H:i:s', $this->getStartTime());?></br>
				<b>Strecke: </b><?php echo  $this->getDistance()?> km</br>
				<b>Dauer: </b><?php echo date('H:i:s',$this->getTimeSpan()-$this->UTCOffset)?></br>
				<b>Minimale Höhe: </b><?php echo  $this->getMinHeight()?> m</br>
				<b>Maximale Höhe: </b><?php echo  $this->getMaxHeight()?> m</br>
				</p>
				]]>
			</description>

		<styleUrl>#lineStylea</styleUrl>
		<LineString>
			<tessellate>1</tessellate>
			<altitudeMode>clampToGround</altitudeMode>
			<coordinates> 
			<?php foreach ($this->getGeoTable() as $row):?>
			<?php echo $row->longitude.','.$row->lattitude.','.$row->height."\n";?>
			<?php endforeach;?>
			</coordinates>
		</LineString>
		</Placemark>
		<?php if ($showEnd):?>
		<Placemark>
			<name><?php echo $this->remove($this->getEndAddress(),$remove);?></name>
			<description>
				<![CDATA[
				<h1><?php echo $this->remove($this->getEndAddress(),$remove);?></h1>
				<p><b>Zeit: </b><?php echo date('d.m.Y H:i:s', $this->getEndTime());?></br>
				<b>Strecke: </b><?php echo  $this->getDistance()?> km</br>
				</p>
				]]>
			</description>
			<Point> 
				<coordinates><?php $row=$this->getGeoTableLastRow();echo $row->longitude.','.$row->lattitude.','.$row->height."\n";?></coordinates>
			</Point>
		</Placemark>
		<?php endif;?>

		</Document>
		</kml>
		<?php



	}
	
	public function merge($in)

	{
		$itemCount=count($in);
		$i=0;
		foreach($in as $key => $item) 
		{
			if ($i==0)
			{
				$this->setStartAddress($item->getStartAddress());
				$this->setLengthStartAddress($item->getLengthStartAddress());
				$this->setCount(0);
				$this->setMinHeight($item->getMinHeight());
				$this->setMaxHeight($item->getMaxHeight());
				$this->setStartTime($item->getStartTime());
				//$this->setTimeSpan(0);
				$this->setGeoTable(array());
				$this->setFileName("");
				$this->setDistance(0);
			}	

			$this->setCount($this->getCount()+$item->getCount());
			if($item->getMaxHeight()>$this->getMaxHeight()) 
				$this->setMaxHeight($item->getMaxHeight());
			if($item->getMinHeight()<$this->getMinHeight()) 
				$this->setMinHeight($item->getMinHeight());
			//$this->setTimeSpan($this->getTimeSpan()+$item->getTimeSpan());
			$this->setGeoTable(array_merge($this->getGeoTable(),$item->getGeoTable()));
			$this->setFileName($this->getFileName()."\t".$item->getFileName());
			$this->setDistance($this->getDistance()+$item->getDistance());


			if ($i==$itemCount-1)
			{
				$this->setEndAddress($item->getEndAddress());
				$this->setLengthEndAddress($item->getLengthEndAddress());
				$this->setEndTime($item->getEndTime());
				$this->setTimeSpan($this->getEndTime()-$this->getStartTime());
			}
			$i++;
		}
		
	}
}
 
//MAIN$
$parameters = array( 't:' => 'required', 'v::' => 'optional:', 's' => 'optional','e' => 'optional:','h' => 'optional:','c::' => 'optional','l::' => 'optional','r::' => 'optional',);

$options = getopt(implode('', array_keys($parameters)), $parameters); 
$pruneargv = array(); 
foreach ($options as $option => $value) 
{ 
	foreach ($argv as $key => $chunk) 
	{ 
		$regex = '/^'. (isset($option[1]) ? '--' : '-') . $option . '/'; 
		if ($chunk == $value && $argv[$key-1][0] == '-' || preg_match($regex, $chunk)) 
		{ 
			array_push($pruneargv, $key); 
		} 
	} 
}
while ($key = array_pop($pruneargv)) unset($argv[$key]); 
if (isset($options["h"]))
{
?>
atravelbookexport Version 1.1
Author: Kai Berggold
Description: Little Script to export the travelbook-data from sygic aura navigation app (Only Tested on android)
It is written in the script language PHP (www.php.net). PHP is mostly used for programming dynamic websites, but can also be used for other things...
What you have to do: Export the log-files from the folder aura/res/travelbook from the sd card on yout computer (with a php-interpreter), and then apply script.
Under  Linux this can usually done by installing php via the package management system of the distribuion you use, making the script executable and running it on the command shell.

Tested on the aura version 11.2.3 (use -v2) and an the version present at June 2011 (use -v1)

Usage:   atravelbookexport [options] filename [filenames]

Basic options:
 -t	Output type (required)
 -tt	Output as table (tab-seperated)
 -ts	Output as short table (tab-seperated)
 -tk	Output as KML-Filename
 -v	Version of Sygic Aura; currently 1 for the Version of April 2011
 -s	Show Start-Placemark in KML-Filename
 -e	Show End-Placemark in KML-Filename
 -c	Use different line Color (e.g. -c99AAFF00)
 -l	Use different line thickness (e.g. -l1)
 -r 	Only show the last n Words of the Addresses (For only showing the city) in the KML File (e.g. -r1)
 -h	Show this help

Examples:

KML-output:
atravelbookexport.php -v1 -tk   -l4 -s  -r1 -c330066FF  110419_165356.log >output.kml

<?php	
	exit();
}
if (isset($options["s"]))
	$showStart=True;
else 
	$showStart=False;
if (isset($options["e"]))
	$showEnd=True;
else 
	$showEnd=False;	
if (isset($options["c"]))
	$color=$options["c"];
else 
	$color="770000FF";
if (isset($options["l"]))
	$lineWidth=$options["l"];
else 
	$lineWidth=3;

if (isset($options["r"]))
	$remove=$options["r"];
else 
	$remove=0;

$fileNames=array();
$i=1;
foreach($argv as $key=>$arg) 
{
	if($key!="0")
	{
		$fileNames[$i]=$arg;
		$i++;
	}
}
$tracks=array();
$fileCount=count($argv);
// echo "filecount".$fileCount."\n";
for($i=1;$i<$fileCount;$i++) 
{
// 	echo $fileNames[$i]."\n";
	$tracks[$i]=new auraconvert($options);
	$tracks[$i]->readAuraFile($fileNames[$i]);
}
if(isset($options["t"]))
{
	if ($options["t"]!="s")
	{
		$mergedTrack=new auraconvert($options);
		$mergedTrack->merge($tracks);
	}
	if ($options["t"]=="t")
		$mergedTrack->tableOutput();
	if ($options["t"]=="s")
		$tracks[1]->tableHeaderOutput($tracks);

	elseif ($options["t"]=="k")
		$mergedTrack->kmlOutput($showStart,$showEnd,$color, $lineWidth,$remove);
}
?>
