<?php
	## Function to generate pseudo-random unique IDs
	function randomhex($length)
	{
		$key = "";
		for ( $i=0; $i < $length; $i++ )
		{
			 $key .= dechex( rand(0,15) );
		}
		return $key;
	}

	## Metadata
	require_once("../libs/providers/orange/client/idpMetadata.php");
	$issuer = "SVOAFRA19A33F788FCE4";
	$idpTargetUrl = $idpMetadata['http://otvp.auth-int.orange.fr']['SingleSignOnUrl'];

	## Dynamic data of the SAML request
	$id = randomhex(32);
	$issueInstant = gmdate("Y-m-d\TH:i:s\Z");

	## <AuthnRequest>
	$authnRequest =
		"<AuthnRequest xmlns=\"urn:oasis:names:tc:SAML:2.0:protocol\" " .
			      	"ID=\"_" . $id . "\" " .
			      	"Version=\"2.0\" " .
				"IssueInstant=\"" . $issueInstant . "\">\n" .
			"<Issuer xmlns=\"urn:oasis:names:tc:SAML:2.0:assertion\">" .
			    $issuer . "</Issuer>\n" .
		"</AuthnRequest>";

	## SAML HTTP-Redirect Binding
	$encodedAuthnRequest = urlencode( base64_encode( gzdeflate( $authnRequest ) ));
	$redirectUrl = $idpTargetUrl . "?SAMLRequest=" . $encodedAuthnRequest;

	## Redirect
	Header("Location: ".$redirectUrl);

?>