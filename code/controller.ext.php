<?php
/**
 * Controller for Sencrypt Module for Sentora 1.0.3
 * Version : 002
 * Author : TGates
 * Additional work by Diablo925
 */

// function to check if certificates exist
function check_for_active_certs($dir)
{
    $result = false;
    if($dh = opendir($dir))
	{
        while(!$result && ($file = readdir($dh)) !== false)
		{
            $result = $file !== "." && $file !== ".." && $file !== "_account" && !is_dir($file);
        }
        closedir($dh);
    }
    return $result;
}
// function to completely remove a domain's cert folder
function delete_folder($target)
{
    if(is_dir($target))
	{
        $files = glob($target.'*', GLOB_MARK );

        foreach($files as $file)
        {
            delete_folder($file);      
        }

        rmdir($target);
    }
	elseif (is_file($target))
	{
        unlink($target);  
    }
}
// for LEscript you can use any logger according to Psr\Log\LoggerInterface
class Logger
{
	function __call($name, $arguments)
	{
		echo date('Y-m-d H:i:s')." [$name] ${arguments[0]}\n";
	}
}
$logger = new Logger();

class module_controller extends ctrl_module
{
		
	static $ok;
	static $error;
	static $delok;
	static $keyadd;
	static $download;
	static $empty;
	
	static function ExecuteDownload($domain, $username)
	{
		set_time_limit(0);
		global $zdbh, $controller;
		$temp_dir = ctrl_options::GetSystemOption('sentora_root') . "etc/tmp/";
		$ssldir = "../../../etc/letsencrypt/live/";
		$backupname = $domain;
		$resault = exec("/etc/letsencrypt/live/" . $domain . "/ && " . ctrl_options::GetSystemOption('zip_exe') . " -r9 " . $temp_dir . $backupname . " *");
		@chmod($temp_dir . $backupname . ".zip", 0777);
		$filename = $backupname . ".zip";
		$filepath = $temp_dir;
		header("Pragma: public");
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Cache-Control: public");
		header("Content-Description: File Transfer");
		header("Content-type: application/octet-stream");
		header("Content-Disposition: attachment; filename=\"".$filename."\"");
		header("Content-Transfer-Encoding: binary");
		header("Content-Length: ".filesize($filepath.$filename));
		ob_end_flush();
		readfile($filepath.$filename);
		unlink($temp_dir . $backupname . ".zip");
		return true;
	}
	
	static function doDownload()
	{
		global $controller;
		$currentuser = ctrl_users::GetUserDetail();
		$formvars = $controller->GetAllControllerRequests('FORM');
		if (self::ExecuteDownload($formvars['inName'], $currentuser["username"]))
		return true;
	}
	
	static function doDelete()
	{
		global $controller;
		runtime_csfr::Protect();
		$currentuser = ctrl_users::GetUserDetail();
		$formvars = $controller->GetAllControllerRequests('FORM');
		if (self::ExecuteDelete($formvars['inName'], $currentuser["username"]))
		return true;
	}
	
	static function ExecuteDelete($domain, $username)
	{
		global $zdbh, $controller;
		$currentuser = ctrl_users::GetUserDetail();

		$dir = "/etc/letsencrypt/live/".$domain;
		
		delete_folder($dir);
		
		if ($domain == ctrl_options::GetSystemOption('sentora_domain'))
		{
			$name = 'global_zpcustom';
			$new = '';
			$line = "# SSL-Sencrypt - START".fs_filehandler::NewLine();
			$line .= 'SSLEngine On' .fs_filehandler::NewLine();
			$line .= "SSLCertificateFile /etc/letsencrypt/live/".$domain."/cert.pem".fs_filehandler::NewLine();
			$line .= "SSLCertificateKeyFile /etc/letsencrypt/live/".$domain."/private.pem".fs_filehandler::NewLine();
			$line .= "SSLCACertificateFile /etc/letsencrypt/live/".$domain."/chain.pem".fs_filehandler::NewLine();

			$line .= "SSLProtocol All -SSLv2 -SSLv3".fs_filehandler::NewLine();
			$line .= "SSLHonorCipherOrder on".fs_filehandler::NewLine();
			$line .= "SSLCipherSuite \"EECDH+ECDSA+AESGCM EECDH+aRSA+AESGCM EECDH+ECDSA+SHA384 EECDH+ECDSA+SHA256 EECDH+aRSA+SHA384 EECDH+aRSA+SHA256 EECDH+AESGCM EECDH EDH+AESGCM EDH+aRSA HIGH !MEDIUM !LOW !aNULL !eNULL !LOW !RC4 !MD5 !EXP !PSK !SRP !DSS\"".fs_filehandler::NewLine();
			$line .= "# SSL-Sencrypt - END".fs_filehandler::NewLine();
			
			$sql = $zdbh->prepare("UPDATE x_settings SET so_value_tx = replace(so_value_tx, :data, :new) WHERE so_name_vc = :name");
			$sql->bindParam(':data', $line);
			$sql->bindParam(':new', $new);
			$sql->bindParam(':name', $name);
			$sql->execute();
					
			$portname = "sentora_port";
			$port = "80";
			$updatesql = $zdbh->prepare("UPDATE x_settings SET so_value_tx = :value WHERE so_name_vc = :name");
			$updatesql->bindParam(':value', $port);
			$updatesql->bindParam(':name', $portname);
			$updatesql->execute();
			
		}
		else
		{

			$port = NULL;
			$portforward = NULL;
			$new = '';
						
			$line = "# SSL-Sencrypt - START".fs_filehandler::NewLine();
			$line .= 'SSLEngine On' .fs_filehandler::NewLine();
			$line .= "SSLCertificateFile /etc/letsencrypt/live/".$domain."/cert.pem".fs_filehandler::NewLine();
			$line .= "SSLCertificateKeyFile /etc/letsencrypt/live/".$domain."/private.pem".fs_filehandler::NewLine();
			$line .= "SSLCACertificateFile /etc/letsencrypt/live/".$domain."/chain.pem".fs_filehandler::NewLine();

			$line .= "SSLProtocol All -SSLv2 -SSLv3".fs_filehandler::NewLine();
			$line .= "SSLHonorCipherOrder on".fs_filehandler::NewLine();
			$line .= "SSLCipherSuite \"EECDH+ECDSA+AESGCM EECDH+aRSA+AESGCM EECDH+ECDSA+SHA384 EECDH+ECDSA+SHA256 EECDH+aRSA+SHA384 EECDH+aRSA+SHA256 EECDH+AESGCM EECDH EDH+AESGCM EDH+aRSA HIGH !MEDIUM !LOW !aNULL !eNULL !LOW !RC4 !MD5 !EXP !PSK !SRP !DSS\"".fs_filehandler::NewLine();
			$line .= "# SSL-Sencrypt - END".fs_filehandler::NewLine();

			$sql = $zdbh->prepare("UPDATE x_vhosts SET vh_custom_tx = replace(vh_custom_tx, :data, :new), vh_custom_port_in=:port, vh_portforward_in=:portforward WHERE vh_name_vc = :domain");
			 
			$sql->bindParam(':data', $line);
			$sql->bindParam(':new', $new);
			$sql->bindParam(':domain', $domain);
			$sql->bindParam(':port', $port);
			$sql->bindParam(':portforward', $portforward);
			$sql->execute();
		}
		self::SetWriteApacheConfigTrue();
		self::$delok = true;
		return true;
	}

	static function doMakenew()
	{
		global $controller;
		runtime_csfr::Protect();
		$currentuser = ctrl_users::GetUserDetail();
		$formvars = $controller->GetAllControllerRequests('FORM');
		if (empty($formvars['inDomain']))
		{ 
			self::$empty = true;
			return false;
		}
		if (self::ExecuteMakessl($formvars['inDomain']))
			return true;
	}
	
	static function ExecuteMakessl($domain)
	{
		global $zdbh, $controller;
		
		// set Lescript vars
		$zsudo = ctrl_options::GetOption('zsudo');
		$currentuser = ctrl_users::GetUserDetail();
		$formvars = $controller->GetAllControllerRequests('FORM');
		$certlocation = "/etc/letsencrypt/live/".$domain."/";
		// convert below to use domain path from DB table instead of assuming domain = domain path
		// get proper domain path from DB
//		$domain_folder = $zdbh->prepare("SELECT vh_directory_vc FROM x_vhosts WHERE vh_name_vc=:domain");
//		$domain_folder->bindParam(':domain', $domain);
//		$domain_folder->execute();
//		$domain_folder = $zdbh->fetchColumn();
//		var_dump($domain_folder);
		$domain_folder = str_replace(".","_", $domain);
		$domain_folder = str_replace("www.","", $domain_folder);
		$username = $currentuser['username'];
		
		$webroot = "/var/sentora/hostdata/".$username."/public_html/".$domain_folder;

// start Lescript		
		if (!defined("PHP_VERSION_ID") || PHP_VERSION_ID < 50300 || !extension_loaded('openssl') || !extension_loaded('curl'))
		{
			die("You need at least PHP 5.3.0 with OpenSSL and curl extension installed.\n");
		}
		
		require("modules/sencrypt/code/Lescript.php");

		// Always use UTC
		date_default_timezone_set("UTC");
		// Make sure our cert location exists
		if (!is_dir($certlocation))
		{
			// Make sure nothing is already there.
			if (file_exists($certlocation))
			{
				unlink($certlocation);
			}
			mkdir ($certlocation);
		}
		try
		{
			$le = new Analogic\ACME\Lescript($certlocation, $webroot, $logger);
			
			// uses client's email used during registration
			$le->contact = array('mailto:' . $currentuser['email']); // optional
			
			$le->initAccount();
			$le->signDomains(array($domain));
			//$le->signDomains(array($domain, 'www.'.$domain));
		}
		catch (\Exception $e)
		{
			$logger->error($e->getMessage());
			$logger->error($e->getTraceAsString());
			// Exit with an error code, something went wrong.
			exit(1);
		}
// end Lescript
		if ($domain == ctrl_options::GetSystemOption('sentora_domain'))
		{
			$line = "# SSL-Sencrypt - START".fs_filehandler::NewLine();
			$line .= 'SSLEngine On' .fs_filehandler::NewLine();
			$line .= "SSLCertificateFile /etc/letsencrypt/live/".$domain."/cert.pem".fs_filehandler::NewLine();
			$line .= "SSLCertificateKeyFile /etc/letsencrypt/live/".$domain."/private.pem".fs_filehandler::NewLine();
			$line .= "SSLCACertificateFile /etc/letsencrypt/live/".$domain."/chain.pem".fs_filehandler::NewLine();

			$line .= "SSLProtocol All -SSLv2 -SSLv3".fs_filehandler::NewLine();
			$line .= "SSLHonorCipherOrder on".fs_filehandler::NewLine();
			$line .= "SSLCipherSuite \"EECDH+ECDSA+AESGCM EECDH+aRSA+AESGCM EECDH+ECDSA+SHA384 EECDH+ECDSA+SHA256 EECDH+aRSA+SHA384 EECDH+aRSA+SHA256 EECDH+AESGCM EECDH EDH+AESGCM EDH+aRSA HIGH !MEDIUM !LOW !aNULL !eNULL !LOW !RC4 !MD5 !EXP !PSK !SRP !DSS\"".fs_filehandler::NewLine();
			$line .= "# SSL-Sencrypt - END".fs_filehandler::NewLine();
			
			$name = 'global_zpcustom';
            $sql = $zdbh->prepare("SELECT * FROM x_settings WHERE so_name_vc  = :name");
            $sql->bindParam(':name', $name);
            $sql->execute();
            while ($row = $sql->fetch())
			{
				$olddata = $row['so_value_tx'];
			}
			$data = $olddata.$line;
			
			$updatesql = $zdbh->prepare("UPDATE x_settings SET so_value_tx = :value WHERE so_name_vc = :name");
			$updatesql->bindParam(':value', $data);
			$updatesql->bindParam(':name', $name);
			$updatesql->execute();
			$portname = "sentora_port";
			$port = "443";
			$updatesql = $zdbh->prepare("UPDATE x_settings SET so_value_tx = :value WHERE so_name_vc = :name");
			$updatesql->bindParam(':value', $port);
			$updatesql->bindParam(':name', $portname);
			$updatesql->execute();
		
		}
		else
		{
			
			$line = "# SSL-Sencrypt - START".fs_filehandler::NewLine();
			$line .= 'SSLEngine On' .fs_filehandler::NewLine();
			$line .= "SSLCertificateFile /etc/letsencrypt/live/".$domain."/cert.pem".fs_filehandler::NewLine();
			$line .= "SSLCertificateKeyFile /etc/letsencrypt/live/".$domain."/private.pem".fs_filehandler::NewLine();
			$line .= "SSLCACertificateFile /etc/letsencrypt/live/".$domain."/chain.pem".fs_filehandler::NewLine();

			$line .= "SSLProtocol All -SSLv2 -SSLv3".fs_filehandler::NewLine();
			$line .= "SSLHonorCipherOrder on".fs_filehandler::NewLine();
			$line .= "SSLCipherSuite \"EECDH+ECDSA+AESGCM EECDH+aRSA+AESGCM EECDH+ECDSA+SHA384 EECDH+ECDSA+SHA256 EECDH+aRSA+SHA384 EECDH+aRSA+SHA256 EECDH+AESGCM EECDH EDH+AESGCM EDH+aRSA HIGH !MEDIUM !LOW !aNULL !eNULL !LOW !RC4 !MD5 !EXP !PSK !SRP !DSS\"".fs_filehandler::NewLine();
			$line .= "# SSL-Sencrypt - END".fs_filehandler::NewLine();
			
			$port = "443";
			$portforward = "1";
			
            $sql = $zdbh->prepare("SELECT * FROM x_vhosts WHERE vh_name_vc = :domain AND vh_deleted_ts IS NULL");
            $sql->bindParam(':domain', $domain);
            $sql->execute();
            while ($row = $sql->fetch()) { $olddata = $row['vh_custom_tx']; }
			$data = $olddata.$line;
			
        	$sql = $zdbh->prepare("UPDATE x_vhosts SET vh_custom_tx=:data, vh_custom_port_in=:port, vh_portforward_in=:portforward WHERE vh_name_vc = :domain");
        	$sql->bindParam(':data', $data);
			$sql->bindParam(':domain', $domain);
			$sql->bindParam(':port', $port);
			$sql->bindParam(':portforward', $portforward);
        	$sql->execute();
		}
		self::SetWriteApacheConfigTrue();
		self::$ok = true;	
		return true;	
	}

	static function ListDomains($uid)
	{
		global $zdbh, $controller;
		$currentuser = ctrl_users::GetUserDetail($uid);
		$sql = "SELECT * FROM x_vhosts WHERE vh_acc_fk=:userid AND vh_enabled_in=1 AND vh_deleted_ts IS NULL ORDER BY vh_name_vc ASC";
		$numrows = $zdbh->prepare($sql);
		$numrows->bindParam(':userid', $currentuser['userid']);
		$numrows->execute();

		if ($numrows->fetchColumn() <> 0)
		{
			$sql = $zdbh->prepare($sql);
			$sql->bindParam(':userid', $currentuser['userid']);
			$res = array();
			$sql->execute();
			if($currentuser["username"] == "zadmin")
			{
				$name = ctrl_options::GetSystemOption('sentora_domain');
				$res[] = array('domain' => "$name");
			}
			while ($rowdomains = $sql->fetch())
			{
				$res[] = array('domain' => $rowdomains['vh_name_vc']);
			}
			
			$letsEncryptCerts = "../../../etc/letsencrypt/live/";
			// create SSL folder if not exist
			if (!is_dir($letsEncryptCerts))
			{
				mkdir($letsEncryptCerts, 0777);
			}
	
			if(substr($letsEncryptCerts, -1) != "/") $letsEncryptCerts .= "/";
			// get list of all active SSL certificates
			$d = @dir($letsEncryptCerts);
			while(false !== ($entry = $d->read()))
			{
				if($entry[0] == ".") continue;
				$sslDomains[] = array("domain" => "$entry");
			}
			$d->close();
	
			// extract non-matching client domains from active SSL domains
			foreach($res as $aV)
			{
				$aTmp1[] = $aV['domain'];
			}
			
			foreach($sslDomains as $aV)
			{
				$aTmp2[] = $aV['domain'];
			}

			$nonSSLlist = array_diff($aTmp1,$aTmp2);
			// create new multidimentional array
			$result = array();
			foreach ($nonSSLlist as $row)
			{
			   $result[]['domain'] = $row;
			}

			return $result;
		}
		else
		{
			return false;
		}
	}
	
	static function getDomainList()
	{
		$currentuser = ctrl_users::GetUserDetail();
		return self::ListDomains($currentuser['userid']);
	}
	
	static function ListSSL($uname)
	{
		global $zdbh, $controller;
		$currentuser = ctrl_users::GetUserDetail($uid);
		$sql = "SELECT * FROM x_vhosts WHERE vh_acc_fk=:userid AND vh_enabled_in=1 AND vh_deleted_ts IS NULL ORDER BY vh_name_vc ASC";
		$numrows = $zdbh->prepare($sql);
		$numrows->bindParam(':userid', $currentuser['userid']);
		$numrows->execute();
		if ($numrows->fetchColumn() <> 0)
		{
			$sql = $zdbh->prepare($sql);
			$sql->bindParam(':userid', $currentuser['userid']);
			$res = array();
			$sql->execute();
			if($currentuser["username"] == "zadmin")
			{
				$name = ctrl_options::GetSystemOption('sentora_domain');
				$usersDomains[] = array('domain' => "$name");
			}
			while ($rowdomains = $sql->fetch())
			{
				$usersDomains[] = array('domain' => $rowdomains['vh_name_vc']);
			}

			// set some folders up if they do not exist
			$letsEncriptCerts = "../../../etc/letsencrypt/live/";
		
			if (!is_dir($letsEncriptCerts))
			{
				mkdir($letsEncriptCerts, 0777);
			}

			if(substr($letsEncriptCerts, -1) != "/") $letsEncriptCerts .= "/";
			// need to cross reference user's domains with matching ssl domain folders
			$d = @dir($letsEncriptCerts);
			
			while (false !== ($entry = $d->read()))
			{
				if($entry[0] == ".") continue;
				$sslDomains[] = array("name" => "$entry");
			}
			$d->close();

			// get user's domains
			$currentUserDomains = self::ListDomains($currentuser['uid']);
	
			// convert key from 'domain' to 'name'
			array_walk($usersDomains, function (&$key) {
			   $key['name'] = $key['domain'];
			   unset($key['domain']);
			});

			// extract non-matching client domains from active SSL domains
			foreach($usersDomains as $aV)
			{
				$aTmp1[] = $aV['name'];
			}
			
			foreach($sslDomains as $aV)
			{
				$aTmp2[] = $aV['name'];
			}

			$nonSSLlist = array_intersect($aTmp1,$aTmp2);
			
			// for possible future use
			// check if user's domain folders have any certificates
//			foreach ($nonSSLlist as $row) {
//				// returns false if folder has no certs else true if folder has certs
//			   $hasSSL[]['domain'] = check_for_active_certs($letsEncriptCerts.$row);
//			}
//			var_dump($hasSSL);
			// create new multidimentional array
			$result = array();
			foreach ($nonSSLlist as $row)
			{
			   $result[]['name'] = $row;
			}
			return $result;
		}
		else
		{
			return false;
		}
	}

	static function getSSLList()
	{
		$currentuser = ctrl_users::GetUserDetail();
		return self::ListSSL($currentuser['username']);
	}

	static function SetWriteApacheConfigTrue()
	{
		global $zdbh;
		$sql = $zdbh->prepare("UPDATE x_settings SET so_value_tx='true' WHERE so_name_vc='apache_changed'");
		$sql->execute();
	}

	static function getResult()
	{
		if (self::$ok)
		{
			return ui_sysmessage::shout(ui_language::translate("Your FREE SSL Certificate has been made. It will be active in about 5 minutes."), "zannounceok");
		}
		if (self::$delok)
		{
			return ui_sysmessage::shout(ui_language::translate("The selected certificate has been deleted."), "zannounceerror");
		}
		if (self::$error)
		{
			return ui_sysmessage::shout(ui_language::translate("A certificate with that name already exists."), "zannounceerror");
		}
		if (self::$empty)
		{
			return ui_sysmessage::shout(ui_language::translate("An empty field is not allowed."), "zannounceerror");
		}
		// remove
		if (self::$keyadd)
		{
			return ui_sysmessage::shout(ui_language::translate("Certificate Signing Request was made and sent to the mail you have entered"), "zannounceok");
		}
		return;
	}

    static function getCopyright()
	{
        $copyright = '<font face="ariel" size="2">'.ui_module::GetModuleName().' v0.0.2 &copy; 2016-'.date("Y").' by <a target="_blank" href="http://forums.sentora.org/member.php?action=profile&uid=2">TGates</a> for <a target="_blank" href="http://sentora.org">Sentora Control Panel</a>&nbsp;&#8212;&nbsp;Help support future development of this module and donate today!</font>
<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_blank">
<input type="hidden" name="cmd" value="_s-xclick">
<input type="hidden" name="hosted_button_id" value="DW8QTHWW4FMBY">
<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donate_SM.gif" width="70" height="21" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
<img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">
</form>';
        return $copyright;
    }
}
?>