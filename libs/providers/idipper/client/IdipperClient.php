<?php

require_once __DIR__ . '/../../../../config/config.php';

class IdipperClient {
	
	public function __construct() {
	}
	
	public function getUtilisateur(UtilisateurRequest $utilisateurRequest) {
		$utilisateurReponse = NULL;
		$url = $utilisateurRequest->getUrl();
		$curl_options = array(
				CURLOPT_URL => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HEADER  => false
		);
		if(	null !== (getEnv('PROXY_HOST'))
				&&
			null !== (getEnv('PROXY_PORT'))
		) {
			$curl_options[CURLOPT_PROXY] = getEnv('PROXY_HOST');
			$curl_options[CURLOPT_PROXYPORT] = getEnv('PROXY_PORT');
		}
		if(	null !== (getEnv('PROXY_USER'))
				&&
			null !== (getEnv('PROXY_PWD'))
		) {
			$curl_options[CURLOPT_PROXYUSERPWD] = getEnv('PROXY_USER').":".getEnv('PROXY_PWD');
		}
		$CURL = curl_init();
		curl_setopt_array($CURL, $curl_options);
		$content = curl_exec($CURL);
		$httpCode = curl_getinfo($CURL, CURLINFO_HTTP_CODE);
		curl_close($CURL);
		if($httpCode == 200) {
			$utilisateurReponse = new UtilisateurReponse($content);
		} else {
			config::getLogger()->addError("API CALL : getting Utilisateur, code=".$httpCode);
			throw new Exception("API CALL : getting Utilisateur, code=".$httpCode." is unexpected...");
		}
		return($utilisateurReponse);
	}
	
}

class UtilisateurRequest {
	
	private $ExternalUserID;
	
	public function setExternalUserID($id) {
		$this->ExternalUserID = $id;
	}
	
	public function getExternalUserID() {
		return($this->ExternalUserID);
	}
	
	public function getUrl() {
		$url = 'http://afrostream.wapmmc.com/connecteurs/api.asp';
		$params = array();
		$params['Action'] = 'GET_CUSTOMER';
		$params['IDService'] = '216';
		if(isset($this->ExternalUserID)) {
			$params['ExternalUserID'] = $this->ExternalUserID;
		}
		$url.= "?".http_build_query($params);
		return($url);
	}
	
}

class UtilisateurReponse {
	
	private $IDUtilisateur;
	private $Rubriques = array();
	
	public function __construct($response) {
		$xml = simplexml_load_string($response);
		if($xml === false) {
			config::getLogger()->addError("API CALL : getting Utilisateur, XML cannot be loaded, response=".(string) $response);
			throw new Exception("API CALL : getting Utilisateur, XML cannot be loaded, response=".(string) $response);
		}
		$json = json_encode($xml);
		$tags = json_decode($json, TRUE);
		$IDUtilisateur = $tags['Utilisateur']['IDUtilisateur'];
		if(!isset($IDUtilisateur)) {
			config::getLogger()->addError("API CALL : getting Utilisateur, IDUtilisateur was not found");
			throw new Exception("API CALL : getting Utilisateur, IDUtilisateur was not found");
		}
		$this->setIDUtilisateur($IDUtilisateur);
		foreach($tags['Rubriques'] as $rubriqueElement) {
			$rubrique = new Rubrique();
			$rubrique->setIDRubrique($rubriqueElement['IDRubrique']);
			$rubrique->setPrix($rubriqueElement['Prix']);
			$rubrique->setDuree($rubriqueElement['Duree']);
			$rubrique->setAbonne($rubriqueElement['Abonne']);
			$rubrique->setCreditExpiration($rubriqueElement['CreditExpiration']);
			$rubrique->setCreditExpirationWithGracePeriod($rubriqueElement['CreditExpirationWithGracePeriod']);
			$rubrique->setURLDesabonnement($rubriqueElement['URLDesabonnement']);
			$this->addRubrique($rubrique);
		}
	}
	
	public function setIDUtilisateur($id) {
		$this->IDUtilisateur = $id;
	}
	
	public function getIDUtilisateur() {
		return($this->IDUtilisateur);
	}
	
	public function addRubrique(Rubrique $rubrique) {
		$this->Rubriques[] = $rubrique;
	}
	
	public function getRubriques() {
		return($this->Rubriques);
	}
	
}

class Rubrique {
	
	private $IDRubrique;
	private $Prix;
	private $Duree;
	private $Abonne;
	private $CreditExpiration;
	private $CreditExpirationWithGracePeriod;
	private $URLDesabonnement;
	
	public function setIDRubrique($id) {
		$this->IDRubrique = $id;
	}
	
	public function getIDRubrique() {
		return($this->IDRubrique);
	}
	
	public function setPrix($prix) {
		$this->prix = $prix;
	}
	
	public function getPrix() {
		return($this->prix);
	}
	
	public function setDuree($val) {
		$this->duree = $val;
	}
	
	public function getDuree() {
		retrurn($this->duree);
	}
	
	public function setAbonne($abonne) {
		$this->abonne = $abonne;
	}
	
	public function getAbonne() {
		return($this->abonne);
	}
	
	public function setCreditExpiration($date) {
		$this->CreditExpiration = $date;
	}
	
	public function getCreditExpiration() {
		return($this->CreditExpiration);
	}
	
	public function setCreditExpirationWithGracePeriod($date) {
		$this->CreditExpirationWithGracePeriod = $date;
	}
	
	public function getCreditExpirationWithGracePeriod() {
		return($this->CreditExpirationWithGracePeriod);
	}
	
	public function setURLDesabonnement($url) {
		$this->URLDesabonnement = $url;
	}
	
	public function getURLDesabonnment() {
		return($this->URLDesabonnement);
	}
	
}

?>