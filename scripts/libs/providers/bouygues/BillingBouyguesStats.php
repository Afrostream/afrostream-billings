<?php

require_once __DIR__ . '/../../global/BillingStats.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../../libs/db/dbExports.php';

class BillingBouyguesStats extends BillingStats {
	
	protected function doGenerateStats(DateTime $from, DateTime $to) {
		$fromDate = clone $from;
		$toDate = clone $to;
		$out = array();
		$moreOneDay = new DateInterval("P1D");
		while($fromDate < $toDate) {
			$startingDay = clone $fromDate;
			$startingDay->setTime(0, 0, 0);
			$endingDay = clone $fromDate;
			$endingDay->setTime(23, 59, 59);
			ScriptsConfig::getLogger()->addInfo("retrieving stats for provider ".$this->provider->getName().
					" - date=".$startingDay->format("Ymd")."...");
			//WARNING : Les fichiers contiennent les données d’usages à J-2. (La date du nom correspond à J-1. Ex : le fichier
			//généré le 27 janvier se nomme LECTURE_QUOT_VD8_20140126.csv et contient les données du 25 janvier.
			//
			$filenameDate = clone $startingDay;
			$filenameDate->add($moreOneDay);
			//
			$filename = "EPC_QUOT_SVOD_VAS_". $filenameDate->format("Ymd").".csv";
			$url = getEnv('BOUYGUES_FTP_STATS_PROTOCOL')."://".getEnv('BOUYGUES_FTP_STATS_USER');
			if(strlen(getEnv('BOUYGUES_FTP_STATS_PWD')) > 0) {
				$url.= ":".getEnv('BOUYGUES_FTP_STATS_PWD');
			}
			$url.= "@".getEnv('BOUYGUES_FTP_STATS_HOST').":".getEnv('BOUYGUES_FTP_STATS_PORT');
			$url.= "/out/".$filename;
			//
			$curl_options = array(
				CURLOPT_URL => $url,
				CURLOPT_RETURNTRANSFER => true);
			if(getEnv('BOUYGUES_FTP_STATS_PROXY_ENABLED') == 1) {
				if(	null !== (getEnv('BOUYGUES_PROXY_HOST'))
					&&
					null !== (getEnv('BOUYGUES_PROXY_PORT'))
				) {
					$curl_options[CURLOPT_HTTPPROXYTUNNEL] = true;
					$curl_options[CURLOPT_PROXY] = getEnv('BOUYGUES_PROXY_HOST');
					$curl_options[CURLOPT_PROXYPORT] = getEnv('BOUYGUES_PROXY_PORT');
				}
				if(	null !== (getEnv('BOUYGUES_PROXY_USER'))
					&&
					null !== (getEnv('BOUYGUES_PROXY_PWD'))
				) {
					$curl_options[CURLOPT_PROXYUSERPWD] = getEnv('BOUYGUES_PROXY_USER').":".getEnv('BOUYGUES_PROXY_PWD');
				}
			}
			if(getEnv('BOUYGUES_FTP_STATS_PROTOCOL') == 'ftp') {
				$curl_options[CURLOPT_PROTOCOLS] = CURLPROTO_FTP;
			} else if(getEnv('BOUYGUES_FTP_STATS_PROTOCOL') == 'sftp') {
				$curl_options[CURLOPT_PROTOCOLS] = CURLPROTO_SFTP;
				$curl_options[CURLOPT_SSH_PRIVATE_KEYFILE] = getEnv('BOUYGUES_FTP_STATS_PRIVATE_KEY_FILE');
			} else {
				throw new Exception("protocol ".getEnv('BOUYGUES_FTP_STATS_PROTOCOL')." is not supported");
			}
			$curl_options[CURLOPT_VERBOSE] = true;
			$CURL = curl_init();
			curl_setopt_array($CURL, $curl_options);
			//
			$content = curl_exec($CURL);
			$fileExists = false;
			if($content === false) {
				if(curl_errno($CURL) == 78) {
					ScriptsConfig::getLogger()->addInfo("file named '".$filename."' not found");
				} else {
					//Exception
					throw new Exception("curl exception, error_message=".curl_error($CURL).", error_code=".curl_errno($CURL));
				}
			} else {
				$fileExists = true;
			}
			curl_close($CURL);
			//
			if($fileExists) {
				//
				$csvDelimiter = ';';
				$fields = NULL;
				if(($fields = str_getcsv($content,$csvDelimiter)) === false) {
					throw new Exception("cannot read file named '".$filename."' as csv");
				}
				//fields[0] = "AFROSTREAM" | EMPTY
				//fields[1] = "SVOD" | EMPTY
				//fields[2] = "Pass Afrostream" | EMPTY
				//fields[3] = INTEGER | EMPTY  : new
				//fields[4] = INTEGER | EMPTY : expired
				//fields[5] = INTEGER | EMPTY : total
				$data = new BillingStatsData();
				$data->setDate($startingDay);
				$data->setProviderId($this->provider->getId());
				//new
				$data->setSubsNew(intval($fields[3]));
				//expired
				$data->setSubsExpired(intval($fields[4]));
				//total
				$data->setSubsTotal(intval($fields[5]));
				//
				$out[] = $data;
				ScriptsConfig::getLogger()->addInfo("retrieved stats for provider ".$this->provider->getName().
						" - date=".$startingDay->format("Ymd").
						" : total=".$data->getSubsTotal().
						", new=".$data->getSubsNew().
						", expired=".$data->getSubsExpired());
			} else {
				ScriptsConfig::getLogger()->addInfo("retrieved stats for provider ".$this->provider->getName().
						" - date=".$startingDay->format("Ymd"). " bypassed, no data");
			}
			//DONE
			$fromDate = $fromDate->add($moreOneDay); 
		}
		return($out);
	}
	
}

?>