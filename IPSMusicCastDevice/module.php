<?php
define('__ROOT__', dirname(dirname(__FILE__)));
require_once(__ROOT__ . '/libs/autoload.php');

class IPSMusicCastDevice extends IPSModule
{
public function Create()
{
        parent::Create();
		$this->RegisterPropertyString('DeviceID', ''); //Device ID
		$this->RegisterPropertyString('Host', ''); //Device IP
		$this->RegisterPropertyString('Name', ''); //Device Name
		$this->RegisterPropertyBoolean('Coordinator', false); //Is Device a Coordinator?
		

		$this->RegisterVariableBoolean("Power", "Power");
		IPS_SetVariableCustomProfile($this->GetIDForIdent("Power"), "~Switch");
		IPS_SetPosition($this->GetIDForIdent("Power"), 0);
		$this->EnableAction("Power");
		
		$this->RegisterVariableInteger("State", "State");
		IPS_SetVariableCustomProfile($this->GetIDForIdent("State"), "MUC_State");
		IPS_SetPosition($this->GetIDForIdent("State"), 1);
		$this->EnableAction("State");
		
		$this->RegisterVariableInteger("Previous", "Previous");
		IPS_SetVariableCustomProfile($this->GetIDForIdent("Previous"), "MUC_Previous");
		IPS_SetPosition($this->GetIDForIdent("Previous"), 2);
		$this->EnableAction("Previous");
		
		$this->RegisterVariableInteger("Next", "Next");
		IPS_SetVariableCustomProfile($this->GetIDForIdent("Next"), "MUC_Next");
		IPS_SetPosition($this->GetIDForIdent("Next"), 3);
		$this->EnableAction("Next");
		
		$this->RegisterVariableInteger("Volume", "Volume");
		IPS_SetPosition($this->GetIDForIdent("Volume"), 4);
		IPS_SetVariableCustomProfile($this->GetIDForIdent("Volume"), "MUC_Volume");
		$this->EnableAction("Volume");
		
		$this->RegisterVariableBoolean("Mute", "Mute");
		IPS_SetVariableCustomProfile($this->GetIDForIdent("Mute"), "MUC_Mute");
		IPS_SetPosition($this->GetIDForIdent("Mute"), 5);
		$this->EnableAction("Mute");
		
		$this->RegisterVariableBoolean("Shuffle", "Shuffle");
		IPS_SetVariableCustomProfile($this->GetIDForIdent("Shuffle"), "~Switch");
		IPS_SetPosition($this->GetIDForIdent("Shuffle"), 6);
		$this->EnableAction("Shuffle");
		
		$this->RegisterVariableBoolean("Repeat", "Repeat");
		IPS_SetVariableCustomProfile($this->GetIDForIdent("Repeat"), "~Switch");
		IPS_SetPosition($this->GetIDForIdent("Repeat"), 7);
		$this->EnableAction("Repeat");

		$this->RegisterVariableInteger("Input", "Input");
		IPS_SetPosition($this->GetIDForIdent("Input"), 8);
		$this->EnableAction("Input");
		
		$this->RegisterVariableString("Playtime", "Play Time");
		IPS_SetPosition($this->GetIDForIdent("Playtime"), 9);
		
		$this->RegisterVariableString("Title", "Title");
		IPS_SetPosition($this->GetIDForIdent("Title"), 10);
		
		$this->RegisterVariableString("Artist", "Artist");
		IPS_SetPosition($this->GetIDForIdent("Artist"), 11);
		
		$this->RegisterVariableString("Album", "Album");
		IPS_SetPosition($this->GetIDForIdent("Album"), 12);
		
		$this->RegisterVariableString("AlbumArt", "Cover");
		IPS_SetPosition($this->GetIDForIdent("AlbumArt"), 13);
		IPS_SetVariableCustomProfile($this->GetIDForIdent("AlbumArt"), "~HTMLBox");

		// register update timer for Device subscription
		$this->RegisterTimer('subscribeDevicesTimer', 2000, 'MUC_subscribeDevice($_IPS[\'TARGET\']);');

		// register onetime-timer for Device Setup
		$this->RegisterTimer('setupOneTimeTimer', 2000, 'MUC_DeviceSetup($_IPS[\'TARGET\']);');
    }

public function Destroy()
{
        parent::Destroy();
		//Funktioniert nicht, warum?
		
		/*if (IPS_VariableProfileExists("MUC_Input_" . $this->ReadPropertyString('DeviceID')))
		{
			IPS_DeleteVariableProfile("MUC_Input_" . $this->ReadPropertyString('DeviceID'));
		}*/
    }

public function ApplyChanges()
{
        parent::ApplyChanges();
		$this->ConnectParent("{82347F20-F541-41E1-AC5B-A636FD3AE2D8}");
		SetValueInteger($this->GetIDForIdent("Previous"),1);
		SetValueInteger($this->GetIDForIdent("Next"),1);
    }

protected function getMusicCastNetworkObj()
{
		return new MusicCast\Network;
}

protected function getMusicCastClientObj()
{
	try {
		$DeviceIP = $this->ReadPropertyString('Host');
		return new MusicCast\Client(['host' => $DeviceIP,'port' => 80,]);
	}
	catch (Exception $e) {
		echo 'Error: ',  $e->getMessage(), "\n";
		$this->SetStatus(104);
		exit(1);
	}
}

protected function getMusicCastDeviceObj()
{
	try {
		$DeviceIP = $this->ReadPropertyString('Host');
		return new MusicCast\Device($DeviceIP);
	}
	catch (Exception $e) {
		echo 'Error: ',  $e->getMessage(), "\n";
		$this->SetStatus(104);
		exit(1);
	}
}

protected function getMusicCastSpeakerObj($MUCDeviceObj)
{
	try {
		return new MusicCast\Speaker($MUCDeviceObj);
	}
	catch (Exception $e) {
		echo 'Error: ',  $e->getMessage(), "\n";
		$this->SetStatus(104);
		exit(1);
	}
}

protected function getMusicCastControllerObj($MUCControllerObj,$MUCNetworkObj)
{
	try {
		return new MusicCast\Controller($MUCControllerObj,$MUCNetworkObj,1);
	}
	catch (Exception $e) {
		echo 'Error: ',  $e->getMessage(), "\n";
		$this->SetStatus(104);
		exit(1);
	}
}


public function subscribeDevice()
	{
		IPS_LogMessage("MUC ". $this->ReadPropertyString('Name'), "Subscribe Device: " . $this->ReadPropertyString('Host'));
		$musicCastClientObj = $this->getMusicCastClientObj();
		$result = $musicCastClientObj->api('events')->subscribe();
		$timer = 540000; //9 Minuten
		$this->SetTimerInterval('subscribeDevicesTimer', $timer);
	}

public function updateSpeakerIP()
{
		//Clear cache
		$tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "musiccast";
		@$this->delete_files($tempPath);
		//Compare IPs
		$CurrentIP = $this->getSpeakerIPbyName($this->ReadPropertyString('Name'));
		if($CurrentIP != $this->ReadPropertyString('Host'))
			{
				IPS_LogMessage("MUC " . $this->ReadPropertyString('Name'), "Device IP updated");
				SetValue($this->GetIDForIdent('Input'),$CurrentIP);
			} else {
				IPS_LogMessage("MUC " . $this->ReadPropertyString('Name'), "Device IP is still valid");
			}
		$this->SetStatus(102);
}
//Get Data from UDP Socket
public function ReceiveData($JSONString)
{
			$data = json_decode($JSONString);
			//Parse and write values to our buffer
			$this->SetBuffer("MusicCastDeviceBuffer", utf8_decode($data->Buffer));
			$MUC_Buffer = $this->GetBuffer("MusicCastDeviceBuffer");
			$MUC_Buffer_JSON = json_decode($MUC_Buffer,false);
			//print_r($MUC_Buffer_JSON);
			if ($MUC_Buffer_JSON->device_id == $this->ReadPropertyString('DeviceID'))
			{
				if (key($MUC_Buffer_JSON) == 'main') {
					if (property_exists($MUC_Buffer_JSON->main, 'mute')) {
							IPS_LogMessage("MUC " . $this->ReadPropertyString('Name') . " [Main]", $MUC_Buffer_JSON->main->mute);
							$this->SetValue("Mute", $MUC_Buffer_JSON->main->mute);
						}
					if (property_exists($MUC_Buffer_JSON->main, 'volume')) {
							IPS_LogMessage("MUC " . $this->ReadPropertyString('Name') . " [Main] Volume", $MUC_Buffer_JSON->main->volume);
							$this->SetValue("Volume", $MUC_Buffer_JSON->main->volume);
						}
					if (property_exists($MUC_Buffer_JSON->main, 'input')) {
							IPS_LogMessage("MUC " . $this->ReadPropertyString('Name') . " [Main] Input", $MUC_Buffer_JSON->main->input);
							SetValue($this->GetIDForIdent('Input'), $this->getVariableIntegerbyName($this->GetIDForIdent('Input'),$MUC_Buffer_JSON->main->input));
						}
					if (property_exists($MUC_Buffer_JSON->main, 'signal_info_updated')) {
							IPS_LogMessage("MUC " . $this->ReadPropertyString('Name') . " [Main]->signal_info_updated", $MUC_Buffer_JSON->main->signal_info_updated);
							$this->updateSpeakerInfos();
						}	
					if (property_exists($MUC_Buffer_JSON->main, 'power')) {
							IPS_LogMessage("MUC " . $this->ReadPropertyString('Name') . " [Main]->power", $MUC_Buffer_JSON->main->power);
							if ($MUC_Buffer_JSON->main->power == 'on')
							{
								SetValue($this->GetIDForIdent('Power'),true);
								$this->setHiddenDeviceVariable($this->GetIDForIdent('Power'),false);
							}else
							{
								SetValue($this->GetIDForIdent('Power'),false);
								$this->setHiddenDeviceVariable($this->GetIDForIdent('Power'),true);
							}
						}
				}
				if (key($MUC_Buffer_JSON) == 'netusb')
				{
					
					if (property_exists($MUC_Buffer_JSON->netusb, 'play_time')) {
						$playtime = gmdate("H:i:s", $MUC_Buffer_JSON->netusb->play_time);
						$this->SetValue("Playtime", $playtime);
					}
					if (property_exists($MUC_Buffer_JSON->netusb, 'signal_info_updated')) {
						IPS_LogMessage("MUC " . $this->ReadPropertyString('Name') . " [netusb]->signal_info_updated", $MUC_Buffer_JSON->netusb->signal_info_updated);
					}
					if (property_exists($MUC_Buffer_JSON->netusb, 'play_info_updated')) {
						IPS_LogMessage("MUC " . $this->ReadPropertyString('Name') . " [netusb]->player_info_updated", $MUC_Buffer_JSON->netusb->play_info_updated);
						$this->updateSpeakerInfos();
					}
				}
			}
		}
		

public function updateSpeakerInfos()
	{
		$MUCNetworkObj = $this->getMusicCastNetworkObj();
		$MUCDeviceObj = $this->getMusicCastDeviceObj();
		$MUCSpeakerObj = $this->getMusicCastSpeakerObj($MUCDeviceObj);
		$MUCControllerObj = $this->getMusicCastControllerObj($MUCSpeakerObj,$MUCNetworkObj,1);
		//Update Album Details
		$StateDetails = $MUCControllerObj->getStateDetails();
		$this->SetValue("Title", $this->getObjProp($StateDetails->track,"title"));
		$this->SetValue("Artist", $this->getObjProp($StateDetails->track,"artist"));
		$this->SetValue("Album", $this->getObjProp($StateDetails->track,"album"));
		if ($this->getObjProp($StateDetails->track,"albumArt") == "")
		{
			IPS_SetHidden($this->GetIDForIdent('AlbumArt'),true);
		}else{
			$AlbumArtHTML = "<img src='" . $this->getObjProp($StateDetails->track,"albumArt") . "' style='height: 20%; width: 20%; object-fit: fill;' />";
			$this->SetValue("AlbumArt", $AlbumArtHTML);
			IPS_SetHidden($this->GetIDForIdent('AlbumArt'),false);
		}
		//Update State (play,stop,pause)
		$this->SetValue("State", $MUCControllerObj->getState());
		//Update Power 
		$powerstate = $MUCControllerObj->isPowerOn();
			if($powerstate == false)
			{
				$this->SetValue("Power", $powerstate);
				$this->setHiddenDeviceVariable($this->GetIDForIdent("Power"),true);
				//$this->SetTimerInterval('subscribeDevicesTimer', 0);
			}else{
				$this->SetValue("Power", $powerstate);
				$this->setHiddenDeviceVariable($this->GetIDForIdent("Power"),false);
			}
		
		//Update Volume 
		$this->SetValue("Volume", $MUCControllerObj->getVolume());
		//Update Mute 
		$this->SetValue("Mute", $MUCControllerObj->isMuted());
		//Update Input
		$CurrentInput = $MUCControllerObj->getInput();
		$this->SetValue("Input", $this->getVariableIntegerbyName($this->GetIDForIdent('Input'),$CurrentInput));
		//Update Repeat
		$this->SetValue("Repeat", $MUCControllerObj->getRepeat());
		//Update Shuffle
		$this->SetValue("Shuffle", $MUCControllerObj->getShuffle());
	}

//function extract protected properties
protected function getObjProp($obj, $val){
	$propGetter = Closure::bind( function($prop){return $this->$prop;}, $obj, $obj );
	return $propGetter($val);
}

public function RequestAction($Ident, $Value) {
		$MUCNetworkObj = $this->getMusicCastNetworkObj();
		$MUCDeviceObj = $this->getMusicCastDeviceObj();
		$MUCSpeakerObj = $this->getMusicCastSpeakerObj($MUCDeviceObj);
		$MUCControllerObj = $this->getMusicCastControllerObj($MUCSpeakerObj,$MUCNetworkObj,1);

    switch($Ident) {
        case "Mute":
			if($Value == true)
			{
				$result = $MUCControllerObj->mute();
			}else{
				$result = $MUCControllerObj->unmute();
			}
            break;
        case "Power":
			if($Value == false)
			{
				$result = $MUCControllerObj->standBy();
				$this->setHiddenDeviceVariable($this->GetIDForIdent($Ident),true);
			}else{
				$result = $MUCControllerObj->powerOn();
				$this->setHiddenDeviceVariable($this->GetIDForIdent($Ident),false);
				$this->subscribeDevice();
				$this->updateSpeakerInfos();
			}
            break;
        case "Volume":
			$result = $MUCControllerObj->setVolume($Value);
            break;
        case "State":
			$result = $MUCControllerObj->setState($Value);
            break;
        case "Input":
			$InputName = $this->getVariableValueName($this->GetIDForIdent($Ident),$Value);
			$result = $MUCControllerObj->setInput($InputName);
            break;
        case "Previous":
			{$result = $MUCControllerObj->previous();}
            break;
        case "Next":
			{$result = $MUCControllerObj->next();}
            break;
        case "Repeat":
			{$result = $MUCControllerObj->toggleRepeat();}
            break;
        case "Shuffle":
			{$result = $MUCControllerObj->toggleShuffle();}
            break;
        default:
            throw new Exception("Invalid Ident");
    }
}
//Versteckt alle Objekte wenn Power vom Device off ist
protected function setHiddenDeviceVariable($PowerChildrenID,$hide)
{
	$ParentID = IPS_GetParent($PowerChildrenID);
	$ChildrenIDs = IPS_GetChildrenIDs($ParentID);
	foreach ($ChildrenIDs as $ChildrenID)
	{
		if(IPS_GetName($ChildrenID) != "Power")
		{
		IPS_SetHidden($ChildrenID,$hide);
		}
	}
}

//Wird ausgefürt nachdem die Instanze angelegt wurde (durch Timer)
public function DeviceSetup()
{
	$this->SetTimerInterval('setupOneTimeTimer', 0);
	$this->createInputVariablenprofile();
	IPS_SetVariableCustomProfile($this->GetIDForIdent("Input"), "MUC_Input_" . $this->ReadPropertyString('DeviceID'));
	$this->updateSpeakerIP();
	$this->updateSpeakerInfos();
}
protected function getVariableValueName($VariableID,$VariableValue)
{
	$VariableObject = IPS_GetVariable($VariableID);
	$VariableCustomProfileName = $VariableObject['VariableCustomProfile'];
	$VariableProfileObject = IPS_GetVariableProfile($VariableCustomProfileName);
	$VariableProfileValueName = $VariableProfileObject['Associations'][$VariableValue]['Name'];
	return $VariableProfileValueName;
}

protected function getVariableIntegerbyName($VariableID,$VariableValueName)
{
	$i=0;
	$VariableObject = IPS_GetVariable($VariableID);
	$VariableCustomProfileName = $VariableObject['VariableCustomProfile'];
	$VariableProfileObject = IPS_GetVariableProfile($VariableCustomProfileName);
	foreach($VariableProfileObject['Associations'] as $VariableProfileObjectSub)
	{
		if ($VariableProfileObjectSub['Name'] == $VariableValueName)
		{
			$VariableProfileValueInt = $i;
		}
		$i++;
	}
	
	return $VariableProfileValueInt;
}

protected function getSpeakerIPbyName($SpeakerName)
{
		$MUCNetworkObj = $this->getMusicCastNetworkObj();
		try {
				$speaker = $MUCNetworkObj->getSpeakerByName($SpeakerName);
				$DeviceObj = $this->getObjProp($speaker,"device");
				$DeviceIP = $this->getObjProp($DeviceObj,"ip");
				return $DeviceIP;
			}
		catch (Exception $e) {
				$this->SetStatus(104);
				echo 'Error: ',  $e->getMessage(), "\n";
				exit(1);
		}
}

//Input Variable Profile create
protected function createInputVariablenprofile()
	{
		$MUCNetworkObj = $this->getMusicCastNetworkObj();
		$MUCDeviceObj = $this->getMusicCastDeviceObj();
		$MUCSpeakerObj = $this->getMusicCastSpeakerObj($MUCDeviceObj);
		$MUCControllerObj = $this->getMusicCastControllerObj($MUCSpeakerObj,$MUCNetworkObj,1);

		$DeviceID = $this->ReadPropertyString('DeviceID');
		$Inputs = $MUCControllerObj->getInputList();
		
		$VariablenProfileName = "MUC_Input_" . $DeviceID;
		if (!IPS_VariableProfileExists($VariablenProfileName)) 
		{
			IPS_CreateVariableProfile($VariablenProfileName, 1);
			$i = 0;
			foreach ($Inputs as $Input){
				IPS_SetVariableProfileAssociation($VariablenProfileName, $i, $Input, "Speaker",-1);
				$i++;
			}
		}
	}
	
protected function getInstanceNameExist($InstanceName)
		{
			$Instanceexist = false;
			$Instances = IPS_GetInstanceList();
			foreach($Instances as $Instance)
			{
				$InstanceObject = IPS_GetInstance($Instance);
				$InstanceObjectName = IPS_GetName($InstanceObject['InstanceID']);
				
				if ($InstanceObjectName == $InstanceName) $Instanceexist = true;
			}
			return $Instanceexist;
		}

protected function delete_files($target) {
    if(is_dir($target)){
        $files = glob( $target . '*', GLOB_MARK ); //GLOB_MARK adds a slash to directories returned

        foreach( $files as $file ){
            $this->delete_files( $file );      
        }

        rmdir( $target );
    } elseif(is_file($target)) {
        unlink( $target );  
    }
}
}