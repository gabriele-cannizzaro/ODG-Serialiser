<?php 

# ========================   ODG Serialiser   =========================
# =====================================================================
# Purpose:          Serialise pages and replace strings in ODG document
#                   ---------------------------
# Author:           Gabriele Cannizzaro
# Revsion:          v1.0
#
# 3rd party:        PureCSS [http://purecss.io/]
# ======================================================================

zip_lib_check();

if ($_POST['submit']) {

	###		SETTINGS
	$extract_dir	= './extract/';
	$build_dir		= './rebuild/';

	$sequence		= $_POST['sequence'];
	$replace 		= $_POST['replace'];

	###		Hash the file to generate "unique" string for the processing folder
	$file_hash = md5_file ( $_FILES['odgfile']['tmp_name'] );

	###		Build the processing folder path
	$dir_path = $extract_dir . $file_hash . '/';

	###		Unzip the ODT file
	odt_unpack ( $_FILES['odgfile']['tmp_name'], $dir_path );

	###		Open the 'content.xml' file and read it
	$fp = fopen ( $dir_path . 'content.xml', 'r' );
	$content = fread ( $fp, filesize ( $dir_path . 'content.xml' ) );

	###		Split the content of the file into an array (header, page, footer)
	$page = odg_explode ( $content, '<draw:page', '</draw:page>' );

	###		Initialise the new document content with the original header
	$newdoc = (string) $page[0];

	###		Cycle through the number of iterations specified
	for ( $i = $sequence['start']; $i <= $sequence['end']; $i++ ) {
		###		Replace the first string with the index value
		$cur_page = str_replace ( $sequence['source'], $i, $page[1] );
		###		Replace each subsequent strings with the specified constant value
		foreach ( $replace as $pairs ) {
			$cur_page = str_replace ( $pairs['source'], $pairs['replace'], $cur_page );
		}
		###		Committ the changes to the new document content
		$newdoc .= $cur_page;
	}

	###		Finalise the new document content with the original footer
	$newdoc .= $page[2];

	###		Write the new content to 'content.xml'
	$fp = fopen( $dir_path . 'content.xml', 'w' );
	fwrite ( $fp, $newdoc );
	fclose ( $fp );

	###		Create the new file name
	$newDocumentFile = $build_dir . 'ODGS_' . $_FILES['odgfile']['name'];

	###		Build the new ODT file
	odt_repack ( $dir_path, $newDocumentFile );

	###		Delete the source folder
	rrmdir ( $dir_path );

	###		Send the newly create file for download
	if (file_exists($newDocumentFile)) {
		header('Content-Description: File Transfer');
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename='.basename($newDocumentFile));
		header('Content-Transfer-Encoding: binary');
		header('Expires: 0');
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Pragma: public');
		header('Content-Length: ' . filesize($newDocumentFile));
		ob_clean();
		flush();
		readfile($newDocumentFile);
		unlink($newDocumentFile);
	}
}

function zip_lib_check ( ) {
	if (!extension_loaded('zip')) {
		if (!dl('zip.so')) {
			exit('For this script to work, the PHP ZIP extension must be loaded.');
		}
	}
}

function odt_unpack ( $source, $destination ) {
	$zip = new ZipArchive();
	$zip->open($source);
	$zip->extractTo($destination);
	return $zip->close();
}

function odt_repack ( $source, $destination ) {
	if (file_exists($source) === true) {
		$zip = new ZipArchive();
		if ($zip->open($destination, ZIPARCHIVE::CREATE) === true) {
			$source = realpath($source);
			if (is_dir($source) === true) {
				$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);
				foreach ($files as $file) {
					$file = realpath($file);
					if (is_dir($file) === true) {
						$zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
					}
					else if (is_file($file) === true) {
						$zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
					}
				}
			}
			else if (is_file($source) === true) {
				$zip->addFromString(basename($source), file_get_contents($source));
			}
		}
		return $zip->close();
	}
}

function odg_explode ( $string, $start, $end ) {
	$ini = strpos( $string, $start );
	$len = strpos( $string, $end, $ini) - $ini;
	$len += strlen( $end );
	return array(
		substr( $string, 0, $ini - strlen( $ini ) + 4 ),
		substr( $string, $ini, $len ),
		substr( $string, $ini - strlen ( $ini ) + 4 + $len, strlen ( $string ) )
	);
}

function rrmdir( $dir ) {
	foreach( glob( $dir . '/*' ) as $file ) {
		if ( is_dir( $file ) )
			rrmdir( $file );
		else
			unlink( $file );
	}
	rmdir( $dir );
}

?>

<!DOCTYPE html>

<html>

<head>
	<title>ODG Manipulator</title>
	<style>
		body {
			font-family: "PT Sans";
		}
		table, input {
			width: 100%;
		}

		#container {
			width: 500px;
			margin: 0px auto 0px auto;
		}

		header {
			margin-top: 25px;
		}
		header h1 {
			margin: 0px;
			padding: 0px;
			font-size: 2em;
			text-align: center;
			text-transform: uppercase;
		}

		form p {
			margin: 0px;
		}

		#odgfile {
			margin-top: 25px;
		}

		#sequence {
			margin-top: 25px;
		}
		#sequence-th-source {
			width: 350px;
		}
		#sequence-th-start {
			width: 100px;
		}
		#sequence-th-end {
			width: 50px;
		}

		#replace_strings {
			margin-top: 25px;
		}
		#replace-th-replace {
			width: 243px;
		}
		#replace-th-with {
			width: 242px;
		}
		#replace-th-remove {
			width: 15px;
			cursor: pointer;
		}
		#replace-th-remove:before {
			content: "\271A";
		}
		.removeVar {
			cursor: pointer;
			margin-right: 3px;
		}
		.removeVar:before {
			content: "\2718";
		}

		#submit {
			margin: 35px auto 0px auto;
			display: block;
		}
	</style>
	<link rel="stylesheet" href="http://yui.yahooapis.com/pure/0.4.2/pure-min.css">
	<link href='http://fonts.googleapis.com/css?family=PT+Sans:400,700' rel='stylesheet' type='text/css'>
</head>

<body>

<div id="container">

	<header>
		<h1>ODG Serialiser</h1>
	</header>

	<form id="odg_manipulator" name="odg_manipulator" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST" enctype="multipart/form-data">

		<table class="pure-table pure-table-horizontal" id="odgfile">
		    <thead>
		        <tr>
		            <th id="sequence-th-source">Select an ODG file to serialise</th>
		        </tr>
		    </thead>
		    <tbody>
				<tr>
					<td>
						<input type="file" name="odgfile" accept="application/vnd.oasis.opendocument.graphics" required>
					</td>					
				</tr>
		    </tbody>
		</table>

		<table class="pure-table pure-table-horizontal" id="sequence">
		    <thead>
		        <tr>
		            <th id="sequence-th-source">Replace string with number sequence</th>
		            <th id="sequence-th-start">Start from</th>
		            <th id="sequence-th-end">End at</th>
		        </tr>
		    </thead>
		    <tbody>
				<tr>
					<td>
						<input type="text" name="sequence[source]" id="sequence_source'" value="<?php echo $_POST['sequence']['source']; ?>" placeholder="Source string" required>
					</td>
					<td>
						<input type="number" name="sequence[start]" id="sequence_start" value="<?php echo $_POST['sequence']['start']; ?>" placeholder="start" required>
					</td>
					<td>
						<input type="number" name="sequence[end]" id="sequence_end" value="<?php echo $_POST['sequence']['end']; ?>" placeholder="end" required>
					</td>					
					</tr>
		    </tbody>
		</table>

		<table class="pure-table pure-table-horizontal" id="replace_strings">
		    <thead>
		        <tr>
		            <th id="replace-th-replace">Replace string</th>
		            <th id="replace-th-with">With string</th>
		            <th id="replace-th-remove"></th>
		        </tr>
		    </thead>
		    <tbody>
		    </tbody>
		</table>

		<input type="submit" name="submit" id="submit" value="Generate and download ODG file" class="pure-button pure-button-active">

	</form>
</div>


	<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js"></script>
	<script>
		var $node		=	"";
		varCount		=	0;
		
		$('#replace_strings').on('click', '.removeVar', function(){
			$(this).parent().remove();
		});

		$('#replace-th-remove').on('click', function(){
			varCount++;
			$node = '\
				<tr>\
					<td>\
						<input type="text" name="replace['+varCount+'][source]" id="replace_source_'+varCount+'" placeholder="Source string" required>\
					</td>\
					<td>\
						<input type="text" name="replace['+varCount+'][replace]" id="replace_replace_'+varCount+'" placeholder="New string" required>\
					</td>\
					<td class="removeVar">\
					</td>\
				</tr>\
			';
			$('#replace_strings > tbody:first').prepend($node);
		});
	</script>

</body>
</html>