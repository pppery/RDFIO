<?php
class SPARQLImport extends SpecialPage {

	function __construct() {
		parent::__construct( 'SPARQLImport' );
		$this->triplesPerBatch = 10; // Limits how many triples are loaded per time
	}

	/**
	 * The main code goes here
	 */
	function execute( $par ) {
		global $wgOut, $wgRequest;
		try {
			$this->setHeaders();

			// Figure out the submit button caption, which should change after doing the first batch.
			if ( $wgRequest->getText( 'action' ) === 'import' ) {
				$offset = $wgRequest->getVal( 'offset', 0 );
				$limit = $this->triplesPerBatch;
				$submitButtonText = "Import next $limit triples...";
			} else {
				$submitButtonText = 'Import';
			}
			// Print the form
			$wgOut->addHTML( $this->getHTMLForm( $submitButtonText ) );

			// For now, print the result XML from the SPARQL query
			if ( $wgRequest->getText( 'action' ) === 'import' ) {
				$externalSparqlUrl = $wgRequest->getText( 'extsparqlurl' );
				if ( $externalSparqlUrl === '' ) {
					throw new RDFIOUIException('External SPARQL Url is empty!');
				}
				$sparqlQuery = urlencode( "SELECT DISTINCT * WHERE { ?s ?p ?o } OFFSET $offset LIMIT $limit" );
				$sparqlQueryUrl = $externalSparqlUrl . '/' . '?query=' . $sparqlQuery;
				$sparqlResultXml = file_get_contents($sparqlQueryUrl);
								
				$sparqlResultXmlObj = simplexml_load_string($sparqlResultXml);
				
				// Some output for debugging purposes
				$wgOut->addWikiText("== Result from reading SPARQL ==");
				$wgOut->addHTML("<pre>URL read:     " . $externalSparqlUrl . "\n");
				$wgOut->addHTML("SPARQL query: " . $sparqlQuery . "\n\n");
				$wgOut->addHTML( "Results:\n" );
	
				$importTriples = array();
				
				foreach ($sparqlResultXmlObj->results->children() as $result ) {
					$triple = array();
					// $wgOut->addHTML( print_r($result, true) );
					foreach( $result as $binding ) {
						if ($binding['name'] == 's') {
							$s = (string) $binding->uri[0];
							if ($s == '') {
								throw new Exception('Could not extract subject from empty string (' . print_r($binding->uri, true) . '), in SPARQLImport');
							}
							$triple['s'] = $s; 
							$triple['s_type'] = $this->resourceType($triple['s']);
						} else if ($binding['name'] == 'p') {
							$p = (string) $binding->uri[0];
							if ($p == '') {
								throw new Exception('Could not extract predicate from empty string (' . print_r($binding->uri, true) . '), in SPARQLImport');
							}
							$triple['p'] = $p;
							$triple['p_type'] = $this->resourceType($triple['p']);
						} else if ($binding['name'] == 'o') {
							$o = (string) $binding->uri[0];
							if ($o == '') {
								throw new Exception('Could not extract object from empty string (' . print_r($binding->uri, true) . '), in SPARQLImport');
							}
							$triple['o'] = $o;
							$triple['o_type'] = $this->resourceType($triple['o']);
							$triple['o_datatype'] = '';
						}
					}
					$importTriples[] = $triple;
				}
				
				$rdfImporter = new RDFIORDFImporter();			
				$rdfImporter->importTriples($importTriples);
				
				$wgOut->addHTML( "\n</pre>" );
			} 
		} catch (RDFIOUIException $e) {
			$this->showErrorMessage('Error!', $e->getMessage());
			$wgOut->addHTML( $this->getHTMLForm() );
		}
	}
	
	function resourceType( $resourceStr ) {
		if ( substr($resourceStr, 0, 4 ) === 'http' ) {
			return 'uri';
		} else {
			return 'literal';
		}
	}
	
	protected function getHTMLForm( $buttonText ) {
		global $wgArticlePath, $wgRequest;
		$thisPageUrl = str_replace( '/$1', '', $wgArticlePath ) . "/Special:SPARQLImport";
		$extSparqlUrl = $wgRequest->getText( 'extsparqlurl', 'http://hhpid.bio2rdf.org/sparql' );
		$limit = $this->triplesPerBatch;
		$offset = $wgRequest->getText( 'offset', 0 - $limit ) + $limit;
		$htmlForm = <<<EOD
		<form method="post" action="$thisPageUrl" >
				URL of SPARQL endpoint:<br>
				<input type="hidden" name="action" value="import">
				<input type="text" name="extsparqlurl" size="60" value="$extSparqlUrl"></input>
				<input type="hidden" name="offset" value=$offset>
				<input type="submit" value="$buttonText">
		</form>
EOD;
		return $htmlForm;
	}

	/**
	 * Check whether the current user has rights to edit or create pages
	 */
	protected function userHasWriteAccess() {
		global $wgUser;
		$userRights = $wgUser->getRights();
		return ( in_array( 'edit', $userRights ) && in_array( 'createpage', $userRights ) );
	}

	function showErrorMessage( $title, $message ) {
		global $wgOut;
		$errorHtml = '<div style="margin: .4em 0; padding: .4em .7em; border: 1px solid #FF9999; background-color: #FFDDDD;">
                	 <h3>' . $title . '</h3>
                	 <p>' . $message . '</p>
                	 </div>';
		$wgOut->addHTML( $errorHtml );
	}
	

}