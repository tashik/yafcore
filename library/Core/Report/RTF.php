<?php

// RTF Generator Class 2.5 Build 200
// updated 29 august 2006
// Email: paggard@paggard.com

//error_reporting  (E_ERROR | E_PARSE | E_USER_ERROR); // uncomment this if you wand to avoid "Warnings"


class Core_Report_RTF {

	var $temp_dir;
	var $rnd_proc_nm;

	var $default_units;

	var $header = "";
	var $text = "";

	var $pg_width;
	var $pg_height;
	var $mar_left;
	var $mar_right;
	var $mar_top;
	var $mar_bott;
	var $facing_pages;
	var $gutter_width;
	var $rtl_gutter;

	var $image_size;

	var $header_align;
	var $footer_align;
	var $head_y;
	var $foot_y;
	var $page_numbers;
	var $page_numbers_valign;
	var $page_numbers_align;
	var $pn_autoInsert;

	var $font_face;
	var $font_size;
	var $def_par_before;
	var $def_par_after;
	var $def_par_align;
	var $def_par_lines;
	var $def_par_lindent;
	var $def_par_rindent;
	var $def_par_findent;
	var $tbl_def_border;
	var $tbl_def_width;
	var $tbl_def_align;
	var $tbl_def_valign;
	var $tbl_def_bgcolor;
	var $row_def_align;
	var $img_def_border;
	var $img_def_src;
	var $img_def_width;
	var $img_def_height;
	var $img_def_left;
	var $img_def_top;
	var $img_def_space;
	var $img_def_align;
	var $img_def_wrap;
	var $img_def_anchor;

	var $h_link_fontf;
	var $h_link_fonts;
	var $h_link_fontd;
//-------------------------------------------------------------------------------------------------
	function __construct($inc_file, $pg_orientation="portrait") {
		$slash = "--%345pag1223%--";
		if ($this->f_check($inc_file)) {
			include $inc_file;
		}
		else { die("Wrong File.");}

		//$this->temp_dir = "tmp/";
		$this->temp_dir = $temp_dir;
		$this->rnd_proc_nm = str_replace(' ', '_', microtime());

		$this->default_units = $default_units;

		$this->pg_width=$this->twips($pg_width);
		$this->pg_height=$this->twips($pg_height);
		$this->mar_left=$this->twips($mar_left);
		$this->mar_right=$this->twips($mar_right);
		$this->mar_top=$this->twips($mar_top);
		$this->mar_bott=$this->twips($mar_bott);

		$this->facing_pages=$facing_pages;
		$this->rtl_gutter=$rtl_gutter;
		$this->gutter_width=$this->twips($gutter_width);

		$this->header_align = $header_align;
		$this->footer_align = $footer_align;

		$this->image_size = $image_size;

		$this->head_y=$this->twips($head_y);
		$this->foot_y=$this->twips($foot_y);
		$this->page_numbers = $page_numbers;
		$this->page_numbers_valign = $page_numbers_valign;
		$this->page_numbers_align = $page_numbers_align;
		$this->pn_autoInsert = $page_numbers_autoinsert;

		$this->font_face=$font_face;
		$this->font_size=$font_size;
		$this->def_par_before=$def_par_before;
		$this->def_par_after=$def_par_after;
		$this->def_par_align=$def_par_align;
		$this->def_par_lines=$def_par_lines;
		$this->def_par_lindent=$def_par_lindent;
		$this->def_par_rindent=$def_par_rindent;
		$this->def_par_findent=$def_par_findent;
		$this->tbl_def_border=$tbl_def_border;
		$this->tbl_def_width=$tbl_def_width;
		$this->tbl_def_cellpadding=$tbl_def_cellpadding;
		$this->tbl_def_align=$tbl_def_align;
		$this->tbl_def_valign=$tbl_def_valign;
		$this->tbl_def_bgcolor=$tbl_def_bgcolor;
		$this->row_def_align=$row_def_align;
		$this->img_def_border=$img_def_border;
		$this->img_def_src=$img_def_src;
		$this->img_def_width=$img_def_width;
		$this->img_def_height=$img_def_height;
		$this->img_def_left=$img_def_left;
		$this->img_def_top=$img_def_top;
		$this->img_def_space=$img_def_space;
		$this->img_def_align=$img_def_align;
		$this->img_def_wrap=$img_def_wrap;
		$this->img_def_anchor=$img_def_anchor;

		$this->h_link_fontf=$h_link_fontf;
		$this->h_link_fonts=$h_link_fonts;
		$this->h_link_fontd=$h_link_fontd;

		$this->_first_par = 1;

		$hlink = $this->get_rtf_color(preg_replace("/\#/","",$h_link_color));

		$this->header = "{\\rtf1\\ansi\\deff0\\deftab720

{\\fonttbl
{\\f0\\fnil MS Sans Serif;}
{\\f1\\froman\\fcharset2 Symbol;}
{\\f2\\fswiss\\fprq2\\fcharset".$default_charset."{\\*\\fname Arial;}Arial;}
{\\f3\\froman\\fprq2\\fcharset".$default_charset."{\\*\\fname Times New Roman;}Times New Roman;}
{\\f4\\fmodern\\fcharset".$default_charset."\\fprq1{\\*\\panose 02070309020205020404}Courier New;}
{\\f5\\fswiss\\fcharset".$default_charset."\\fprq2{\\*\\panose 020b0604020202020204}Microsoft Sans Serif;}
{\\f6\\froman\\fcharset".$default_charset."\\fprq2{\\*\\panose 02020404030301010803}Garamond;}
{\\f7\\froman\\fcharset".$default_charset."\\fprq2{\\*\\panose 02020404030301010999}Verdana;}
{\\f8\\froman\\fcharset".$default_charset."\\fprq2{\\*\\panose 02020404030301010888}Courier;}
{\\f9\\fswiss\\fcharset".$default_charset."\\fprq2{\\*\\panose 02020404030301010812}Helvetica;;}
{\\f10\\fnil\\fcharset2\\fprq2{\\*\\panose 05000000000000000000}Wingdings;}
{\\f11\\froman\\fcharset2\\fprq2{\\*\\panose 05020102010507070707}Wingdings 2;}
{\\f12\\froman\\fcharset2\\fprq2{\\*\\panose 05040102010807070707}Wingdings 3;}
{\\f20\\fswiss\\fcharset".$default_charset."\\fprq2{\\*\\panose 02020404030301010558}Arial Narrow;}
{\\f21\\fnil\\fcharset".$default_charset."\\fprq2{\\*\\panose 02000400000000000000}Futura Medium;}
{\\f22\\fswiss\\fcharset".$default_charset."\\fprq2{\\*\\panose 020b0604030504040204}Tahoma;}
}

{\\colortbl;
\\red0\\green0\\blue0;
\\red0\\green0\\blue255;
\\red0\\green255\\blue255;
\\red0\\green255\\blue0;
\\red255\\green0\\blue255;
\\red255\\green0\\blue0;
\\red255\\green255\\blue0;
\\red255\\green255\\blue255;
\\red0\\green0\\blue128;
$hlink
goesuserscolors
}


{\\info
{\\title Paggard}
{\\author paggard}
{\\operator paggard@dlight.ru}
}\r\n
";
		$this->header.= "\\".$this->font($this->font_face)."\\fs".($this->font_size * 2)."\r\n";
		$orient = "";
		if ($pg_orientation == "landscape") {
			$this->header.= "\\paperw".$this->pg_height."\\paperh".$this->pg_width;
			$temp_w = $this->pg_width;
			$this->pg_width = $this->pg_height;
			$this->pg_height = $temp_w;
			$orient = "\\sectd\\lndscpsxn";
		}
		else {
			//$this->header.= "\\paperw".$this->pg_width."\\paperh".$this->pg_height;
		}
		$this->header.= "\\paperw".$this->pg_width."\\paperh".$this->pg_height;
		$this->header.= "\\margl".$this->mar_left."\\margr".$this->mar_right."\\margt".$this->mar_top."\\margb".$this->mar_bott."\\headery".$this->head_y."\\footery".$this->foot_y."\r\n";
		if ($this->facing_pages===1) {
			$this->header.= "\\facingp\\gutter".$this->gutter_width."\r\n";
			if ($this->rtl_gutter) {
				$this->header.= "\\rtlgutter";
			}
			$this->header.= "\r\n";
		}
		if ($this->page_numbers >= 0) {
//			$pgn_y = ($this->page_numbers_valign == "top") ? $this->head_y : $this->pg_height-$this->foot_y;
//			switch ($this->page_numbers_align) {
//				case "center": $pgn_x = round($this->pg_width / 2); break;
//				case "right": $pgn_x = $this->mar_left; break;
//				case "left": $pgn_x = $this->pg_width - $this->mar_right; break;
//			}
//			if ($this->pn_autoInsert != "0") {$pageCoordinates = "\\pgnx".$pgn_x."\\pgny".$pgn_y;}
//			else {$pageCoordinates = "";}


			$pageCoordinates = $this->get_page_numbers();

			$this->header.= $orient."\\pgncont".$pageCoordinates."\\pgndec\\pgnstarts".$this->page_numbers."\\pgnrestart"."\r\n";
		}
	} // end of function
// ------------------------------------------------------------------------------------------------

// ------------------------------------------------------------------------------------------------
	function add_time_log($txt) {
		$this->_time_log .= $txt."   :".$this->_timer->ReturnTime()."\n";
	} // end of function
// ------------------------------------------------------------------------------------------------

// ------------------------------------------------------------------------------------------------
	function get_page_numbers($mode="\\",$lnd=false) {
		$p_width = $this->pg_width;$p_height = $this->pg_height;
		if ($lnd) {
			$p_width = $this->pg_height;$p_height = $this->pg_width;
		}
		$pgn_y = ($this->page_numbers_valign == "top") ? $this->head_y : $p_height-$this->foot_y;
		switch ($this->page_numbers_align) {
			case "center": $pgn_x = round($p_width / 2); break;
			case "right": $pgn_x = $this->mar_left; break;
			case "left": $pgn_x = $p_width - $this->mar_right; break;
		}
		if ($this->pn_autoInsert != "0") {$pageCoordinates = $mode."pgnx".$pgn_x.$mode."pgny".$pgn_y;}
		else {$pageCoordinates = "";}
		return $pageCoordinates;
	} // end of function
// ------------------------------------------------------------------------------------------------

//-------------------------------------------------------------------------------------------------
	function f_check($file) {
		if (!file_exists($file)) { die("<b>Wrong path to the settings file - rtf_config.inc. <br>Script is terminated</b>"); return false; }
		else { return true; }
	} // end of function
// ------------------------------------------------------------------------------------------------

// ------------------------------------------------------------------------------------------------
	function b_style($style) {
		$perms = "";
		switch (strtoupper($style)) {
			case "SHADOWED": $perms .= "brdrsh"; break; //Shadowed border.
			case "DOUBLE": $perms .= "brdrdb"; break; //Double border.
			case "DOTTED": $perms .= "brdrdot"; break; //Dotted border.
			case "DASHED": $perms .= "brdrdash"; break; //Dashed border.
			case "HAIRLINE": $perms .= "brdrhair"; break; //Hairline border.
			case "INSET": $perms .= "brdrinset"; break; //Inset border.
			case "DASH": $perms .= "brdrdashsm"; break; //Dash border (small).
			case "DOT": $perms .= "brdrdashd"; break; //Dot dash border.
			case "DDDASH": $perms .= "brdrdashdd"; break; //Dot dot dash border.
			case "OUTSET": $perms .= "brdroutset"; break; //Outset border.
			case "TRIPLE": $perms .= "brdrtriple"; break; //Triple border.
			//case "Thick": $perms .= "brdrtnthsg"; break; //Thick thin border (small).
			//case "Thin": $perms .= "brdrthtnsg"; break; //Thin thick border (small).
			//case "Thin": $perms .= "brdrtnthtnsg"; break; //Thin thick thin border (small).
			//case "Thick": $perms .= "brdrtnthmg"; break; //Thick thin border (medium).
			//case "Thin": $perms .= "brdrthtnmg"; break; //Thin thick border (medium).
			//case "Thin": $perms .= "brdrtnthtnmg"; break; //Thin thick thin border (medium).
			//case "Thick": $perms .= "brdrtnthlg"; break; //Thick thin border (large).
			//case "Thin": $perms .= "brdrthtnlg"; break; //Thin thick border (large).
			//case "Thin": $perms .= "brdrtnthtnlg"; break; //Thin thick thin border (large).
			case "WAVY": $perms .= "brdrwavy"; break; //Wavy border.
			case "DOUBLEW": $perms .= "brdrwavydb"; break; //Double wavy border.
			case "STRIPED": $perms .= "brdrdashdotstr"; break; //Striped border.
			case "EMBOSS": $perms .= "brdremboss"; break; //Emboss border.
			case "ENGRAVE": $perms .= $slash."brdrengrave"; break; //Engrave border.
		}
		return $perms;
	} // end of function
// ------------------------------------------------------------------------------------------------


//-------------------------------------------------------------------------------------------------
	function def_par() {
		$before = "\\sb".$this->twips($this->def_par_before);
		$after = "\\sa".$this->twips($this->def_par_after);
		$align = "\\q".$this->def_par_align;
		$lines = "\\sl".$this->twips($this->def_par_lines);
		$lindent = "\\li".$this->twips($this->def_par_lindent);
		$rindent = "\\ri".$this->twips($this->def_par_rindent);
		$findent = "\\fi".$this->twips($this->def_par_findent);
		return $before.$after.$align.$lines.$lindent.$rindent.$findent;
	} // end of function
// ------------------------------------------------------------------------------------------------

//-------------------------------------------------------------------------------------------------
	function font($font) {
		$perm = false;
		switch (strtolower($font)) {
			case "sym": $perm = "f1 "; break;
			case "symbol": $perm = "f1 "; break;
			case "arial": $perm = "f2 "; break;
			case "roman": $perm = "f3 "; break;
			case "courier": $perm = "f4 "; break;
			case "seriff": $perm = "f5 "; break;
			case "garamond": $perm = "f6 "; break;
			case "verdana": $perm = "f7 "; break;
			case "cur": $perm = "f8 "; break;
			case "helvetica": $perm = "f9 "; break;
			case "wingdings": $perm = "f10 "; break;
			case "wingdings2": $perm = "f11 "; break;
			case "wingdings3": $perm = "f12 "; break;
			case "arial_narrow": $perm = "f23 "; break;
			case "futura": $perm = "f21 "; break;
			case "tahoma": $perm = "f22 "; break;
		}
		return $perm;
	} // end of function
// ------------------------------------------------------------------------------------------------

// ------------------------------------------------------------------------------------------------
	function get_align($variable) {
		switch (strtolower($variable)) {
			case "center": $variable = "c"; break;
			case "left": $variable = "l"; break;
			case "right": $variable = "r"; break;
			case "justify": $variable = "j"; break;
		}
		return $variable;
	} // end of function
// ------------------------------------------------------------------------------------------------

//-------------------------------------------------------------------------------------------------
	function twips($num) { // great thanks to Ian M. Nordby for this function
		//added units recognition -- assumes 1pt=1/72in exactly (IMN)...
		if (preg_match('/^(-?[0-9]+(\.[0-9]+)?)[ ]?(mm|cm|q|kyu|in|pt|pts|picas|twips)$/i',trim($num),$regs)) {
			$units = strtolower($regs[3]);
			$num = (float)$regs[1];
		}
		else {
			$units = $this->default_units;
		}
		switch ($units) { //unit type
			case 'cm'   : $sum = round($num*567); break; //centimeters (actual ~566.929)
			case 'mm'   : $sum = round($num*56.7); break; //millimeters (=1/10 cm)
			case 'q'    : //alias of 'kyu'
			case 'kyu'  : $sum = round($num*14.175); break; //Q/kyu (=1/4 mm)
			case 'in'   : $sum = round($num*1440); break; //inches
			case 'pt'   : //alias of 'pts' (points)
			case 'pts'  : $sum = round($num*20); break; //pt/pts (=1/72 in)
			case 'picas': $sum = round($num*240); break; //picas (=12 pts or 1/6 in)
			case 'twips': $sum = round($num); break; //twips (=1/20 pt or 1/1440 in)
		}
		return $sum;
	} // end of function
// ------------------------------------------------------------------------------------------------

//-------------------------------------------------------------------------------------------------
	function get_rtf() {
		if ($this->temp_dir !== false) {
			//$rtf = join("",file($this->temp_dir.$this->rnd_proc_nm."_final"));
			@unlink($this->temp_dir.$this->rnd_proc_nm."_final");
			trigger_error ("If you are using temporary directory you need to call either <b>get_rtf_stream(\"file_name\");</b> or <b>get_rtf_to_file(\"path_to_file\",\"file_name\");</b>. Please, consult documentation for additional information.<br>", E_USER_ERROR);
			exit;
		}
		else {
			$rtf = $this->header."\r\n".$this->text."\r\n}";
		}
		//$rtf = preg_replace("/[\r\n]+/"," ",$rtf);
		return $rtf;
	} // end of function
// ------------------------------------------------------------------------------------------------

// ------------------------------------------------------------------------------------------------
	function get_rtf_stream($file_name) {
		$handle = fopen ($this->temp_dir.$this->rnd_proc_nm."_final", "rb");
		do {
			$data = fread($handle, 8192);
			if (strlen($data) == 0) break;
			echo $data;
			empty($data);
		} while(true);
		fclose ($handle);
		@unlink($this->temp_dir.$this->rnd_proc_nm."_final");
	} // end of function
// ------------------------------------------------------------------------------------------------

// ------------------------------------------------------------------------------------------------
	function get_rtf_to_file($path,$file_name) {
		if (!copy($this->temp_dir.$this->rnd_proc_nm."_final", $path.$file_name)) {
			trigger_error ("Failed to copy the file to the given destination : '".$path.$file_name."'<br>\n", E_USER_ERROR);
		}
		@unlink($this->temp_dir.$this->rnd_proc_nm."_final");
	} // end of function
// ------------------------------------------------------------------------------------------------

//-------------------------------------------------------------------------------------------------
	function r_encode($string)
	{ return rawurlencode($string); }
//-------------------------------------------------------------------------------------------------

//-------------------------------------------------------------------------------------------------
	function toCyr($string,$InTbl=0) {
		$this->_first_par=1;
		$fig_l = "pag456pag";
		$fig_r = "pag654pag";
		$slash = "pp345pag1223pp";
		$star = "pp346pag1224pp"; // *
		$quote = "pp375pag1225pp"; // "

		$prgrf = $slash."";
		$string = preg_replace("/[\r\n\t]+/","",$string);

			if (preg_match_all("/(<font)(.*?)(>)/msi", $string, $fonts))
			{
				$text = preg_split("/(<font)(.*?)(>)/msi",$string);
				$fonts = $fonts[2];
				$string = "";
				for ($i=0;$i<sizeof($text);$i++) {
					parse_str(strtolower(preg_replace("/ +/msi", "&", @trim($fonts[$i]))));
					$perms = "";
					if (isset($face)) {
						$perms .= $slash.$this->font($face); unset($face);
					}
					if (isset($size)) {
							$size = $size * 2;
							$perms .= $slash."fs".$size." "; unset($size);
					}
					if (isset($color)) {
							$perms .= $slash."cf".$color." "; unset($color);
					}
					if ($perms == "") { $rer = "";} else {$rer = $fig_l.$perms;}
					$string .= $text[$i].$rer;
				}
				$string = eregi_replace("</font>", $fig_r, $string);
        unset($fonts,$text); //ADDED
			}
	//-----------------------------------------------------------------------------------------------
			if (preg_match_all("/(<a)(.*?)(>)/msi", $string, $links))
			{
				$text = preg_split("/(<a)(.*?)(>)/msi",$string);
				$links = $links[2];
				$string = "";
				for ($i=0;$i<sizeof($text);$i++) {
					unset($alt);$local = "";$file = "";
					$tmp = preg_match("/(alt=\")([^\"]*)(\")/",@trim($links[$i]),$all);
					$links[$i] = ereg_replace("&", "@@##@@", @trim($links[$i]));
					parse_str(strtolower(ereg_replace(" +", "&", @trim($links[$i]))));
					$local = preg_replace("/\"/","",$local);
					//$alt = $all[2];
					$perms = "";
					$perms .= $fig_l.$slash."field".$fig_l.$slash.$star.$slash."fldinst  ";
					if ($local != "") {
						$perms .= "HYPERLINK  ".$slash.$slash."l ".$quote.$local.$quote." ";
					}
					else if ($file != "") {
						$perms .= "HYPERLINK  ".$quote.ereg_replace("@@##@@", "&", $file).$quote." ";
					}
					//$perms .= $slash.$slash."o ".$quote.$alt.$quote;
					$perms .= $fig_r;
					$perms .= $fig_l.$slash."fldrslt ";
					if (isset($def)) {
						$perms .= $slash.$this->font($this->h_link_fontf).$slash."fs".($this->h_link_fonts*2).$slash."cf10 ".$slash.$this->h_link_fontd." ";
						unset($def);
					}
					$slash.$this->font($this->h_link_fontf).$slash."fs".($this->h_link_fonts*2).$slash."cf10 ".$slash.$this->h_link_fontd." ";

					$string .= ($i<sizeof($text)-1) ? $text[$i].$perms : $text[$i];
					unset($perms);
				}
				$string = eregi_replace("</a>", $slash."cf0".$fig_r.$fig_r, $string);
			}
			$target_s = $fig_l.$slash.$star.$slash."bkmkstart ";
			$target_e = $fig_l.$slash.$star.$slash."bkmkend ";
			$string = preg_replace("/(<id )([^>]*)(>)/msi",$target_s."\\2".$fig_r.$target_e."\\2".$fig_r,$string);
//-----------------------------------------------------------------------------------------------
			$d_before = $slash."sb".$this->twips($this->def_par_before);
			$d_after = $slash."sa".$this->twips($this->def_par_after);
			$d_align = $slash."q".$this->get_align($this->def_par_align);
			$d_lines = $slash."sl".$this->twips($this->def_par_lines);
			$d_lindent = $slash."li".$this->twips($this->def_par_lindent);
			$d_rindent = $slash."ri".$this->twips($this->def_par_rindent);
			$d_findent = $slash."fi".$this->twips($this->def_par_findent);
			$d_def_par = $d_before.$d_after.$d_align.$d_lines.$d_lindent.$d_rindent.$d_findent;
			//$d_def_par = $before.$after.$align.$lines.$lindent.$rindent.$findent;

			if (preg_match_all("/(<p)(.*?)(>)/msi", $string, $pars)) {
				$text = preg_split("/(<p)(.*?)(>)/msi",preg_replace("/[\r\n]/msi"," ",$string));
				$pars = $pars[2];
				$string = "";
				for ($i=0;$i<sizeof($text);$i++) {
					parse_str(strtolower(preg_replace("/[\s\+]+/s", "&", @trim($pars[$i]))));
					if (isset($align)) {
						switch (strtolower($align)) {
							case "center": $align = "c"; break;
							case "left": $align = "l"; break;
							case "right": $align = "r"; break;
							case "justify": $align = "j"; break;
						}
					}
					if (isset($before)) { $f_before = $slash."sb".$this->twips($before); unset($before); }
					else {$f_before = $d_before;}
					if (isset($after)) { $f_after = $slash."sa".$this->twips($after); unset($after); }
					else {$f_after = $d_after;}
					if (isset($align)) { $f_align = $slash."q".$align; unset($align); }
					else {$f_align = $d_align;}
					if (isset($lines)) { $f_lines = $slash."sl".$this->twips($lines); unset($lines); }
					else {$f_lines = $d_lines;}
					if (isset($lindent)) { $f_lindent = $slash."li".$this->twips($lindent); unset($lindent); }
					else {$f_lindent = $d_lindent;}
					if (isset($rindent)) { $f_rindent = $slash."ri".$this->twips($rindent); unset($rindent); }
					else {$f_rindent = $d_rindent;}
					if (isset($findent)) { $f_findent = $slash."fi".$this->twips($findent); unset($findent); }
					else {$f_findent = $d_findent;}

					if (isset($talign)) {
						$f_talign_ar = preg_split("/[,. ]/",preg_replace("/['\"]/","",$talign));
						unset($talign);
					}
					if (isset($lead)) {
						$f_lead_ar = preg_split("/[,. ]/",preg_replace("/['\"]/","",$lead));
						unset($lead);
					}
					else {$f_lead = "";}
					if (isset($tsize)) {
						$f_tsize_ar = preg_split("/[,. ]/",preg_replace("/['\"]/","",$tsize));
						unset($tsize);
					}
					else {$f_tsize = "";}
					// -------------------------
					$f_tabs = "";
					if (isset($f_tsize_ar)) {
						for ($ll=0;$ll<sizeof($f_tsize_ar);$ll++) {
							if ($f_tsize_ar[$ll]!="") {
								$f_tsize_ar[$ll] = ($f_tsize_ar[$ll]) ? $f_tsize_ar[$ll] : 10;
								switch (@$f_talign_ar[$ll]) {
									case "right": $talign_tmp = $slash."tqr"; break;
									case "center": $talign_tmp = $slash."tqc"; break;
									case "decimal": $talign_tmp = $slash."tqdec"; break;
									default: $talign_tmp = ""; break;
								}
								$f_tabs .= $slash."tl".$f_lead_ar[$ll].$talign_tmp.$slash."tx".$this->twips($f_tsize_ar[$ll]);
							}
						}
					}
					// -------------------------
					$f_par = $f_tabs.$f_before.$f_after.$f_align.$f_lines.$f_lindent.$f_rindent.$f_findent;
					if (isset($par_keep)) {
						$f_par .= $slash."keep";
						unset($par_keep);
					}
					unset ($f_tabs,$f_tsize,$f_lead);//unset($f_lead_ar,$f_tsize_ar);//$f_lead.$f_tsize;
					if ($InTbl) {$mdefp = "";}
					else {$mdefp = $slash."pard";}
					if ($text[$i] == "") {
						$tyu = $mdefp;
					}
					else {
						if ($this->_first_par) {$tyu = $mdefp;}
						else {$tyu = $slash."par".$mdefp;} // FIXES SOMETHING ...
						//$tyu = $mdefp;
						//else {$tyu = $slash."par".$mdefp;}
					}
					$string .= $text[$i];
					if ($i<sizeof($text)-1) {$string .= $tyu.$f_par." ";$this->_first_par=0;}
					unset($tyu);
				}// end of for
        unset($text); // ADDED
			}

	//--- SECTION HANDLE -------------------------------------------------------------------------
			if (preg_match_all("/(<new section)(.*?)(>)/msi", $string, $pars)) {
				$text = preg_split("/(<new section)(.*?)(>)/msi",$string);
				$pars = $pars[2];
	//			$string = "";
				for ($i=0;$i<sizeof($text);$i++) {

					$f_sect = $slash."sect".$slash."sectd".$slash."pgncont";

					parse_str(strtolower(ereg_replace(" +", "&", @trim($pars[$i]))));
					if ($nobreak) {$f_sect .= $slash."sbknone ";}
					if ($columns) {$f_sect .= $slash."cols".$columns." ";}
					else {$f_sect .= $slash."cols1 ";}


					if (isset($landscape) && !isset($portrait)) {
						$f_sect .= $slash."lndscpsxn";
						$f_sect .= $slash."pghsxn".$this->pg_width;
						$f_sect .= $slash."pgwsxn".$this->pg_height;
						$f_sect .= $slash."marglsxn".$this->mar_left;
						$f_sect .= $slash."margrsxn".$this->mar_right;
						$f_sect .= $slash."margtsxn".$this->mar_top;
						$f_sect .= $slash."margbsxn".$this->mar_bott;
						$f_sect .= $slash."headery".$this->head_y.$slash."footery".$this->foot_y;
						$f_sect .= $this->get_page_numbers($slash,1);
					}
					if (!isset($landscape) && isset($portrait)) {
						$f_sect .= $slash."lndscpsxn";
						$f_sect .= $slash."pgwsxn".$this->pg_width;
						$f_sect .= $slash."pghsxn".$this->pg_height;
						$f_sect .= $slash."marglsxn".$this->mar_left;
						$f_sect .= $slash."margrsxn".$this->mar_right;
						$f_sect .= $slash."margtsxn".$this->mar_top;
						$f_sect .= $slash."margbsxn".$this->mar_bott;
						$f_sect .= $slash."headery".$this->head_y.$slash."footery".$this->foot_y;
						$f_sect .= $this->get_page_numbers($slash);
					}
					if (!isset($landscape) && !isset($portrait)) {
						$f_sect .= $slash."margl".$this->mar_left.$slash."margr".$this->mar_right.$slash."margt".$this->mar_top.$slash."margb".$this->mar_bott.$slash."headery".$this->head_y.$slash."footery".$this->foot_y;
						$f_sect .= $this->get_page_numbers($slash);
					}

					if ($pn_start) {
						switch (strtolower($pn_align)){
							case "center": $pgn_x = round($this->pg_width / 2); break;
							case "right": $pgn_x = $this->mar_left; break;
							case "left": $pgn_x = $this->pg_width - $this->mar_right; break;
						}
						$pgn_y = (strtolower($pn_valign) == "top") ? $this->head_y : $this->pg_height-$this->foot_y;
	//					$f_sect = $slash."sect \r\n ";
	//					if ($nobreak) {$f_sect .= $slash."sbknone";}
						if ($this->pn_autoInsert == "1") {$pageCoordinates = $slash."pgnx".$pgn_x.$slash."pgny".$pgn_y;}
						else {$pageCoordinates = "";}

						$f_sect .= $slash."pgncont". $pageCoordinates .$slash."pgndec".$slash."pgnstarts".$pn_start.$slash."pgnrestart ";
						$string = preg_replace("/(<new section)(".$pars[$i].")(>)/msi",$f_sect,$string);
					}
					else {
	//					$f_sect = $slash."sect \r\n";
	//					if ($nobreak) {$f_sect .= $slash."sbknone";}
						$string = preg_replace("/(<new section)(".$pars[$i].")(>)/msi",$f_sect,$string);
					}

					unset($nobreak,$columns,$landscape,$portrait,$pn_start);
				}
			}
//-----------------------------------------------------------------------------------------------
// HR handle
			if (preg_match_all("/(<hr)(.*?)(>)/msi", $string, $hr_perms))
			{
				$text = preg_split("/(<hr)([^>]*?)(>)/msi",$string);
				$hr_perms = $hr_perms[2];
				$string = "";
				for ($i=0;$i<sizeof($text);$i++) {
					parse_str(strtolower(ereg_replace(" +", "&", @trim($hr_perms[$i]))));
					$perms = $slash."brdrb".$slash."brdrs";
					if (isset($style)) {
						$perms .= $slash.$this->b_style($style);
						unset($style);
					}
					if (isset($color)) {
							$perms .= $slash."brdrcf".$color." "; unset($color);
					}
					if (isset($size)) {$perms .= $slash."brdrw".$size; unset($size);}
					else {$perms .= $slash."brdrw15"; unset($size);}
					$perms .= $slash."brsp20  ";
					if (!$this->inTable) {
						$perms .= $slash."par".$slash."pard ";
					}

					if ($perms == "" || $i==sizeof($text)-1) { $rer = "";} else {$rer = $perms;}
					$string .= $text[$i].$rer;
				}
				//$string = eregi_replace("</font>", $fig_r, $string);
			}
//-------------------------------------------------------------------------------------------------

		$string=preg_replace("/<cpagenum>/msi", $slash."chpgn ", $string);
		$string=preg_replace("/<tpagenum>/msi",$slash."field".$fig_l.$slash.$star.$slash."fldinst  NUMPAGES ".$fig_r,$string);
		$string=preg_replace("/\n/msi", "", $string);
		$string=preg_replace("/<RTL>/msi", $slash."rtlch ", $string);
		$string=preg_replace("/<\/RTL>/msi", $slash."ltrch ", $string);
		$string=preg_replace("/<U>/msi", $slash."ul ", $string);
		$string=preg_replace("/<\/U>/msi", $slash."ul0 ", $string);
		$string=preg_replace("/<I>/msi", $slash."i ", $string);
		$string=preg_replace("/<\/I>/msi", $slash."i0 ", $string);
		$string=preg_replace("/<B>/msi", $slash."b ", $string);
		$string=preg_replace("/<\/B>/msi", $slash."b0 ", $string);
		$string=preg_replace("/<BR>/msi", " ".$slash."line ", $string);
		$string=preg_replace("/<SUP>/msi", $fig_l.$slash."super ", $string);
		$string=preg_replace("/<\/SUP>/msi", $fig_r, $string);
		$string=preg_replace("/<SUB>/msi", $fig_l.$slash."sub ", $string);
		$string=preg_replace("/<\/SUB>/msi", $fig_r, $string);
		$string=preg_replace("/<new page>/msi"," ".$slash."page ", $string);
		$string=preg_replace("/<newcol>/msi"," ".$slash."column ", $string);

		$string=preg_replace("/<HEADER>/msi", $fig_l.$slash."header ".$slash."pard".$slash."plain".$slash.$this->font($this->font_face).$slash."fs".($this->font_size * 2)." ".$slash."q".$this->get_align($this->header_align)." ", $string);
		$string=preg_replace("/<\/HEADER>/msi", $slash."par ".$fig_r, $string);
//-------------
		$string=preg_replace("/<FOOTER>/msi", $fig_l.$slash."footer ".$slash."pard".$slash."plain".$slash.$this->font($this->font_face).$slash."fs".($this->font_size * 2)." ".$slash."q".$this->get_align($this->footer_align)." ".$fig_l, $string);
		$string=preg_replace("/<\/FOOTER>/msi", $fig_r.$fig_l.$slash."par ".$fig_r.$fig_r, $string);
// if facing_pages is activated:
		$string=preg_replace("/<HEADERR>/msi", $fig_l.$slash."headerr ".$slash."pard".$slash."plain ".$slash."q".$this->get_align($this->header_align)." ", $string);
		$string=preg_replace("/<\/HEADERR>/msi", $slash."par ".$fig_r, $string);
//-------------
		$string=preg_replace("/<FOOTERR>/msi", $fig_l.$slash."footerr ".$slash."pard".$slash."plain ".$slash."q".$this->get_align($this->footer_align)." ".$fig_l, $string);
		$string=preg_replace("/<\/FOOTERR>/msi", $fig_r.$fig_l.$slash."par ".$fig_r.$fig_r, $string);
//-------------
		$string=preg_replace("/<HEADERL>/msi", $fig_l.$slash."headerl ".$slash."pard".$slash."plain ".$slash."q".$this->get_align($this->header_align)." ", $string);
		$string=preg_replace("/<\/HEADERL>/msi", $slash."par ".$fig_r, $string);
//-------------
		$string=preg_replace("/<FOOTERL>/msi", $fig_l.$slash."footerl ".$slash."pard".$slash."plain ".$slash."q".$this->get_align($this->footer_align)." ".$fig_l, $string);
		$string=preg_replace("/<\/FOOTERL>/msi", $fig_r.$fig_l.$slash."par ".$fig_r.$fig_r, $string);


		$string=preg_replace("/<TAB>/msi", $slash."tab ", $string);
		$string=preg_replace("/<HR>/msi", $slash."brdrb".$slash."brdrs".$slash."brdrw15".$slash."brsp20  ".$slash."par".$slash."pard ", $string);


		$string = preg_replace("/&#([0-9]+)/e","chr('\\1')",$string);
		$string = preg_replace("/&#U([0-9]+)/",$slash."u\\1  ",$string);
		$fin = rawurlencode($string);
		$r_srch = array (
								"'%20'",
								"'%92'",
								"'%'",
								"'\\'5C'",
								"'".$slash."'",
								"'".$fig_l."'",
								"'".$fig_r."'",
								"'".$star."'",
								"'".$quote."'"
							);
		$r_rplc = array (
								" ",
								"%27",
								"\'",
								"\\",
								"\\",
								"{",
								"}",
								"*",
								"\""
							);
		$fin = preg_replace($r_srch,$r_rplc,$fin);
		return $fin;
	} // end of function
// ------------------------------------------------------------------------------------------------

//-------------------------------------------------------------------------------------------------
	function add_text($string)
	{ $this->text .= $this->toCyr($string); }
//-------------------------------------------------------------------------------------------------

//-------------------------------------------------------------------------------------------------
	function par()
	{ $this->text .= "\\par\r\n"; }
//-------------------------------------------------------------------------------------------------

//-------------------------------------------------------------------------------------------------
	function add_tbl($tar, $flg=0, $brd=1, $bld=1, $hlt=1) {
		//$p = ($this->pg_width - ($this->mar_left + $this->mar_right)) / 100;
		$p = round(($this->pg_width - ($this->mar_left + $this->mar_right)) / sizeof($tar[0]));
		$timer = new ArkTimer;
		$ftb = "\\pard\\par";
		for ($i=1;$i<sizeof($tar);$i++)
		{
			$ttt = 0;
			$ftb.="\\trowd\\trqc\\trgaph108\\trrh380\\trleft36\r\n";
			$tmp1 = "\\clvertalt";
			$tmp2 = "";
			for ($r=0;$r<sizeof($tar[0]);$r++)
			{
				//$ttt += round($tar[0][$r] * $p);
				$ttt += $p;

				if ($hlt==1)
				{
					if ($flg==1) { if ($i==1) { $tmp1 .= "\\clcbpat8\\clshdng3000"; } }
					if ($flg==2) { if ($r==0) { $tmp1 .= "\\clcbpat8\\clshdng3000"; } }
				}

				if ($brd==1) { $tmp1 .= "\\clbrdrt\\brdrs\\brdrw10 \\clbrdrl\\brdrs\\brdrw10 \\clbrdrb\\brdrs\\brdrw10 \\clbrdrr\\brdrs\\brdrw10 "; }

				if ($bld==1)
				{
					if ($i==1 && $flg==1) { $tmp2.="\\b"; }
					else
					{	if ($r==0 && $flg==2) { $tmp2.="\\b"; } else { $tmp2.="\\plain"; }	}

				}
				else { $tmp2.="\\plain"; }

				$tmp1 .= "\\cltxlrtb\\cellx".$ttt;
				$tmp2 .= "\\intbl ".$this->toCyr($tar[$i][$r])."\\cell \\pard \r\n";
			}

			$ftb .= $tmp1."\r\n".$tmp2."\\intbl \\row \\pard\r\n";
		}
		$this->text .= $ftb;
		$this->text .= "\\pard\\par ".$timer->ReturnTime();
	} // end of function

// ------------------------------------------------------------------------------------------------
	function get_rtf_color($color) {
		$r = hexdec(substr($color, 0, 2));
		$g = hexdec(substr($color, 2, 2));
		$b = hexdec(substr($color, 4, 2));
		//\red0\green0\blue0;
		return "\\red".$r."\\green".$g."\\blue".$b.";";
	} // end of function
// ------------------------------------------------------------------------------------------------

// ------------------------------------------------------------------------------------------------
	function my_ar_unique($array) {
		sort($array);
		reset($array);
		$newarray = array();
		$i = 0;
		$element = current($array);
		for ($n=0;$n<sizeof($array);$n++){
			if (next($array) != $element){
				$newarray[$i] = $element;
				$element = current($array);
				$i++;
			}
		}
		return $newarray;
} // end of function
// ------------------------------------------------------------------------------------------------

// ------------------------------------------------------------------------------------------------
/////////////////// PARCE HTML
//-------------------------------------------------------------------------------------------------
	function parce_HTML($string) {
		// --- colors ---------
		if (preg_match_all("/(color=\#)([^ >]*)([ >])/msi", $string, $colors))
		{
			//$colors = array_unique($colors[2]);
			$colors = $this->my_ar_unique($colors[2]);
			$c_tbl = "";
			for ($i=0;$i<sizeof($colors);$i++) {
				$c_find[] = "'color=#".$colors[$i]."'si";
				$c_replace[] = "color=".($i+11);
				$c_tbl .= $this->get_rtf_color($colors[$i]);
			}
			$string = preg_replace ($c_find, $c_replace, $string);
			$this->header = preg_replace("/goesuserscolors/",$c_tbl,$this->header);
		}
		// --------------------
			$fig_l = "pag456pag";
			$fig_r = "pag654pag";

		$token = " img12365412img ";
		$final = "";$im_fl = 0;
		if (preg_match_all("/(<img )(.*?)(>)/msi", $string, $imgs))
		{
			$text_mass = preg_split("/(<img )(.*?)(>)/msi",$string);
			$images = $imgs[2];
			$string = "";
			for ($i=0;$i<sizeof($text_mass);$i++) {
				if (isset($images[$i]) && strlen($images[$i]) > 5) {
					$temp_image_data = $this->parce_image($images[$i]);
					if ($this->temp_dir !== false) {
						// saving the image data to a tmp file to save memory
						$fp = fopen($this->temp_dir.$this->rnd_proc_nm."_".$im_fl, "w");
						if (!$fp) {
							trigger_error ("Failed to write into specified temporary directory - check the permissions.<br>\n", E_USER_ERROR);
						}
						fwrite($fp, $temp_image_data);
						@fclose($fp);
					}
					else {
						$img_mass[$im_fl] = $temp_image_data;
					}
					unset($temp_image_data);

					$string .= $text_mass[$i].$token.$im_fl."nort";
					$im_fl++;
				}
				else { $string .= $text_mass[$i]; }
			}
		}

		/// TABLES
		$this->inTable = false;
		if (preg_match_all("/(<table)(.*?)(<\/table>)/msi", $string, $tbls))
		{
			$this->inTable = true;
			$text_mass = preg_split("/(<table.*?<\/table>)/msi",$string);
      unset($string); //ADDED
			$tables = $tbls[2];
      unset($tbls); //ADDED
			for ($i=0;$i<sizeof($text_mass);$i++) {
				$fin_mass[$i] = $this->parce_table_new(@$tables[$i]);
				$in_text = $this->toCyr($text_mass[$i]);
        $text_mass[$i] = ""; // ADDED
				$p_par = (strlen(preg_replace("/[\\ \r\n]/msi","",$in_text))>0) ? "\\par" : "";
//				if ($i>0) {
//					//$final .= $this->toCyr($text_mass[$i])."\\par".$fin_mass[$i];
//					$final .= $this->toCyr($text_mass[$i]).$fin_mass[$i];
//				}
//				else {$final .= $this->toCyr($text_mass[$i]).$fin_mass[$i];}
				//$final .= $this->toCyr($text_mass[$i]).$p_par.$fin_mass[$i];
				$final .= $in_text.$p_par.$fin_mass[$i];
			}
			$this->inTable = false;
		}
		else { $final = $this->toCyr($string); }


		if ($this->temp_dir !== false) {
			$test_fin = preg_split("/".$token."/ms",$final);

			// trying to save memory by using tmp file
			$tmp_file_name = $this->rnd_proc_nm."_final";
			$fp = fopen($this->temp_dir.$tmp_file_name, "w");

			fwrite($fp, $this->header."\r\n");
			fwrite($fp, $test_fin[0]);
			if (sizeof($test_fin)>1) {
				for ($i=1;$i<sizeof($test_fin);$i++) {
					preg_match("/(\d+)nort/",$test_fin[$i],$mtchs);
					$img_num = $mtchs[1];
					unset($mtchs);
					$test_fin[$i] = preg_replace("/".$img_num."nort"."/","",$test_fin[$i]);

					//read the contents of a tmt image data into the final tmp file
					$handle = fopen ($this->temp_dir.$this->rnd_proc_nm."_".$img_num, "rb");
					do {
						$data = fread($handle, 8192);
						if (strlen($data) == 0) break;
						fwrite($fp, $data);
						empty($data);
					} while(true);
					fclose ($handle);
					@unlink($this->temp_dir.$this->rnd_proc_nm."_".$img_num);
					fwrite($fp, $test_fin[$i]);
					empty($test_fin[$i]);
				}
			}
			fwrite($fp, "\r\n}");
			@fclose($fp);
		}
		else {
			if (preg_match_all("/".$token."/ms", $final,$count_i)) {
				$count_i = $count_i[0];
				for ($i=0;$i<sizeof($count_i);$i++) {
					$fnd = $token.$i."nort";
					$final = ereg_replace($fnd,$img_mass[$i],$final);
				}
			}
			$this->text .= $final;
		}

	}// end of function
//-------------------------------------------------------------------------------------------------
////////////////////////////// IMAGE
//-------------------------------------------------------------------------------------------------
	function pixtotwips($pix) {
		return $this->twips($pix * 3.53);
	}// end of function
//-------------------------------------------------------------------------------------------------
	function openimage($image) {
    //return '';
    $image = preg_replace('@^file://@', getStoragePath().'/', $image);
		$sz = 0;$cy = "";
			$fp = @fopen($image, "rb");
      if (!$fp) {
        logVar("Cannot open image file $image");
        return '';
      }
			while (!feof($fp)){
				$cy .= @fread($fp, 1024);
				$sz++;
				if ($sz > $this->image_size) { break; }
			}
			@fclose($fp);
			return bin2hex($cy);
	}// end of function
//-------------------------------------------------------------------------------------------------
	function parce_image($image) {
		$perms = ereg_replace(" +", "&", trim(preg_replace("/&/","#@@#",$image)));
		parse_str($perms);
		if (isset($src)) {$img_src = $src;}
		else if (isset($SRC)) {$img_src = $SRC;}
		else {$img_src = $this->img_def_src;}
		$img_src = preg_replace("/#@@#/","&",$img_src);
		$perms = strtolower(preg_replace("/[\"']/msi","",$perms));
		$perms = strtolower(preg_replace("/[\n\r]/msi"," ",$perms));
		parse_str($perms);

		if (isset($top)) {$img_top = $top;} else {$img_top = $this->img_def_top;}
		if (isset($width)) {$img_width = $width;} else {$img_width = $this->img_def_width;}
		if (isset($height)) {$img_height = $height+$img_top;} else {$img_height = $this->img_def_height+$img_top;}

		if (isset($left)) {$img_left = $left;} else {$img_left = $this->img_def_left;}

		if (isset($border)) {$img_border = $border;} else {$img_border = $this->img_def_border;}
		if (isset($align)) {$img_align = $align;} else {$img_align = $this->img_def_align;}
		if (isset($wrap)) {$img_wrap = $wrap;} else {$img_wrap = $this->img_def_wrap;}
		if (isset($space)) {$img_space = $space;} else {$img_space = $this->img_def_space;}
		if (isset($anchor)) {$img_anchor = $anchor;} else {$img_anchor = $this->img_def_anchor;}

		srand((double)microtime()*1000000);
		$randval = rand(1111,9999);$bliptag = rand();$blipuid = bin2hex(rand());
		//$src = explode(".",ereg_replace("\"","",$img_src));
		//switch (strtoupper($src[sizeof($src)-1])) {

		//script generated images support
		if (isset($script)) {
			switch (strtoupper($script)) {
				case "JPG": $img_type = "jpeg"; $im=true; break;
				case "JPEG": $img_type = "jpeg"; $im=true; break;
				case "PNG": $img_type = "png"; $im=true; break;
			}
		}
		else {
			preg_match_all("/\.(\w+)/", $img_src, $src);
			switch (strtoupper($src[1][sizeof($src[1])-1])) {
				case "JPG": $img_type = "jpeg"; $im=true; break;
				case "JPEG": $img_type = "jpeg"; $im=true; break;
				case "PNG": $img_type = "png"; $im=true; break;
			}
		}
		switch (strtoupper($img_wrap)) {
			case "NO": $img_wrap = 3; break;
			case "AROUND": $img_wrap = 2; break;
			case "UPDOWN": $img_wrap = 1; break;
		}
///////////////////////////////// ALIGN
		switch (strtoupper($img_anchor)) {
			case "PARA": $a_left = true; break;
			case "PAGE": $a_left = 0; break;
			case "MARGIN": $a_left = true; break;
			case "INCELL": $a_left = true; break;
		}
		switch (strtoupper($img_align)) {
			case "RIGHT":
					if ($a_left) { $a_left = $this->mar_right + $this->mar_left; }
					$del = $this->pg_width - $a_left - $this->twips($img_width);
					break;
			case "LEFT":
					$del = 0;
					break;
			case "CENTER":
					if ($a_left) { $a_left = $this->mar_right + $this->mar_left; }
					$del = round((($this->pg_width - $a_left) / 2) - ($this->twips($img_width) / 2));
					break;
		}


////////////////////////////////////////
// RAW PICTURE
		if (isset($raw)) {

			$f_image = "{\\pict\\picscalex100\\picscaley100\\piccropl0\\piccropr0\\piccropt0\\piccropb0\\picw".round($this->twips($img_width)*2)."\\pich".round($this->twips($img_height)*2)."\\picwgoal".$this->twips($img_width)."\\pichgoal".$this->twips($img_height)."\\wmetafile8";

/*
			$f_image="{\\pict\\picscalex100\\picscaley100\\piccropl0\\piccropr0\\piccropt0\\piccropb0\\picw".($this->twips($img_width)*2)."\\pich".($this->twips($img_height)*2)."\\picwgoal".$this->twips($img_width)."\\pichgoal".$this->twips($img_height)."\\pngblip";

*/
			$f_image .= "\\bliptag".$bliptag."{\\*\\blipuid ".$blipuid."}";

			if ($im) { $f_image .= $this->openimage(preg_replace("/\"/msi","",$img_src)); }
			else { $f_image .= $this->openimage("logo.png"); }
			return $f_image."}";
		}
////////////////////////////////////////
//PICTURE PARAMS
		$sps = $this->twips($img_space);
		$sps = $img_space*36004;
		$x1 = $this->twips($img_left)+$del;
		$x2 = $x1 + $this->twips($img_width);
		$y1 = $this->twips($img_top);
		$y2 = $this->twips($img_height);
		////////////////
		$f_image = "{\\shp{\\*\\shpinst\\shpleft".$x1."\\shpright".$x2."\\shptop".$y1."\\shpbottom".$y2;
		$f_image .= "\\shpz0\\shplid".$randval;
		$f_image .= "\\shpwr".$img_wrap."\\shpwrk0";
		$f_image .= "{\\sp{\\sn fLine}{\\sv ".$img_border."}}";
		$f_image .= "{\\sp{\\sn shapeType}{\\sv 75}}";

		if (strtoupper($img_anchor)=="INCELL") {
			$f_image .= "{\\sp{\\sn fLayoutInCell}{\\sv 1}}";
			$f_image .= "{\\sp{\\sn fAllowOverlap}{\\sv 0}}";
		}
		else {
			$f_image .= "\\shpbx".strtolower($img_anchor)."\\shpby".strtolower($img_anchor);
			$f_image .= "{\\sp{\\sn fBehindDocument}{\\sv 1}}";
			$f_image .= "{\\sp{\\sn dxWrapDistLeft}{\\sv ".$sps."}}";
			$f_image .= "{\\sp{\\sn dxWrapDistRight}{\\sv ".$sps."}}";
			$f_image .= "{\\sp{\\sn dyWrapDistTop}{\\sv ".$sps."}}";
			$f_image .= "{\\sp{\\sn dyWrapDistBottom}{\\sv ".$sps."}}";
		}
		$f_image .= "{\\sp{\\sn pib}{\\sv {\\pict\\".$img_type."blip\\picw".round($img_width)."\\pich".round($img_height)."\\picscalex100\\picscaley100";
		$f_image .= "\\bliptag".$bliptag."{\\*\\blipuid ".$blipuid."}";

		if ($im) { $f_image .= $this->openimage(preg_replace("/\"/msi","",$img_src)); }
		else { $f_image .= $this->openimage("logo.png"); }

		$f_image .= "}}}}}";
		return $f_image;
	}// end of function
//-------------------------------------------------------------------------------------------------
////////////////////////////// TABLE
var $tr_hd_mass;
var $tb_wdth=0;
//-------------------------------------------------------------------------------------------------
	function parce_table_new($table) {
		$rowkeep = false;
		$result = "";
			unset($tbl_border);unset($tbl_width);unset($tbl_height);unset($tbl_align);unset($tbl_valign);unset($tbl_bgcolor);unset($bord_color);
			$all_data_head = array();
			$all_data_body = array();
			$all_data_wdth = array();
			$tmp = split(">", $table);

			$p_tbl = "";
			$perms = ereg_replace(" +", "&", @trim($tmp[0]));
			$perms = strtolower($perms);
			parse_str($perms);
			if (isset($cellpadding)) { $tbl_cellpadding = $this->twips($cellpadding); unset($cellpadding); }
			else {$tbl_cellpadding = $this->twips($this->tbl_def_cellpadding);}
			if (isset($border)) { $tbl_border = $border; unset($border); $p_tbl .= "border=".$tbl_border." - "; }
			else {$tbl_border = $this->tbl_def_border;}
			if (isset($width)) { $tbl_width = $width; unset($width); $p_tbl .= "width=".$tbl_width." - "; }
			else {$tbl_width = $this->tbl_def_width;}
			if (isset($align)) { $tbl_align = $align; unset($align); $p_tbl .= "align=".$tbl_align." - "; }
			else {$tbl_align = $this->tbl_def_align;}
			if (isset($valign)) { $tbl_valign = $valign; unset($valign); $p_tbl .= "valign=".$tbl_valign." - "; }
			else {$tbl_valign = $this->tbl_def_valign;}
			if (isset($bgcolor)) { $tbl_bgcolor = $bgcolor; unset($bgcolor); $p_tbl .= "bgcolor=".$tbl_bgcolor." - "; }
			else {$tbl_bgcolor = $this->tbl_def_bgcolor;}
			if (isset($color)) { $tbl_bgccolor = $color; unset($color);}
			else {$tbl_bgccolor = $this->tbl_def_bgcolor;}
			if (isset($bord_color)) {
				$brd_color = "\\brdrcf".$bord_color.""; unset($bord_color);
			}
			if (isset($tablekeep)) {$tbl_keep_all = "\\keep\\keepn "; unset($tablekeep);}
			else {$tbl_keep_all = "";}
			/////////////
			if (ereg("%",$tbl_width)) {
				$yyy = ereg_replace("%", "", $tbl_width);
				$this->tb_wdth = round((($this->pg_width - ($this->mar_left + $this->mar_right)) / 100) * $yyy);
			}
			else { $this->tb_wdth = $this->twips($tbl_width); }
			$cells_wdth = 0;
			$cells_hght = 0;
			/////////////
			$other = substr(strstr($table, ">"), 1);
			if (preg_match_all("/(<tr)(.*?)(<\/tr>)/msi", $other, $trs))
			{
				$trs = $trs[2];
				$tr_all_f = "";
				for ($r=0;$r<sizeof($trs);$r++) {
					$num_t = 0;
					unset($tr_border,$tr_width,$tr_height,$tr_align);
					unset($tr_valign,$tr_bgcolor,$keep_row,$tr_header);
					$tmp2 = split(">", $trs[$r]);
					$keep_row = ($rowkeep) ? "\\trkeep" : "";
					$p_tr = "";
					$perms = ereg_replace(" +", "&", @trim($tmp2[0]));
					$perms = strtolower($perms);
					parse_str($perms);
					if (isset($cellpadding)) { $tr_cellpadding = $this->twips($cellpadding); unset($cellpadding); $p_tr .= "cellpadding=".$tr_cellpadding." - "; }
					else {$tr_cellpadding = $tbl_cellpadding;}
					if (isset($border)) { $tr_border = $border; unset($border); $p_tr .= "border=".$tr_border." - "; }
					else {$tr_border = $tbl_border;}
					if (isset($width)) { $tr_width = $width; unset($width); $p_tr .= "width=".$tr_width." - "; }
					else {$tr_width = $tbl_width;}
					if (isset($height)) { $tr_height = $height; unset($height); $p_tr .= "height=".$tr_height." - "; }
					else {$tr_height = 0;}
					if (isset($align)) { $tr_align = $align; unset($align); $p_tr .= "align=".$tr_align." - "; }
					else {$tr_align = $this->row_def_align;}
					if (isset($valign)) { $tr_valign = $valign; unset($valign); $p_tr .= "valign=".$tr_valign." - "; }
					else {$tr_valign = $tbl_valign;}
					if (isset($bgcolor)) { $tr_bgcolor = $bgcolor; unset($bgcolor); $p_tr .= "bgcolor=".$tr_bgcolor." - "; }
					else {$tr_bgcolor = $tbl_bgcolor;}
					if (isset($color)) { $tr_bgccolor = $color; unset($color);}
					else {$tr_bgccolor = $tbl_bgccolor;}
					if (isset($heading)) { $tr_header = "\\trhdr"; unset($heading);}
					else {$tr_header = "";}

					$other2 = substr(strstr($trs[$r], ">"), 1);
					///////////////////// - ROW
					if (ereg("%",$tr_width)) {
						$yyy = ereg_replace("%", "", $tr_width);
						$tr_twips_wdth = round((($this->pg_width - ($this->mar_left + $this->mar_right)) / 100) * $yyy);
					}
					else { $tr_twips_wdth = $this->twips($tr_width); }
					if ($tr_height!=0){ $tr_twips_height = "\\trrh".$this->twips($tr_height); }
					else {$tr_twips_height = "\\trrh100"; }
					//else { $tr_wdth_f = ""; }

					switch (strtoupper($tbl_align)) {
						case "CENTER": $tbl_all_all = "\\trqc "; break;
						case "LEFT": $tbl_all_all = "\\trql "; break;
						case "RIGHT": $tbl_all_all = "\\trqr "; break;
					}
					//----
					$tr_padding = "\\trpaddl".$tr_cellpadding."\\trpaddt".$tr_cellpadding."\\trpaddb".$tr_cellpadding."\\trpaddr".$tr_cellpadding."\\trpaddfl3\\trpaddft3\\trpaddfb3\\trpaddfr3";
					$tr_res = "\\pard\\trowd".$keep_row.$tr_header.$tbl_all_all.$tr_padding."\\trgaph100".$tr_twips_height."\\trleft36\r\n";
					//----
					$this->tr_hd_mass[$r] = $tr_res;
					////////////////
					$cells_row_hght = 0;
					$cells_row_wdth = 0;
					////////////////
					if (preg_match_all("/(<td)(.*?)(<\/td>)/msi", $other2, $tds)) {
						$gen_cell_wdth = 0;
						$cur_cell_wdth = 0;
						$tds = $tds[2];
						$cells_in_row = sizeof($tds);
						$td_body_res = ""; unset($td_head_res); unset($td_wdth_mass);
						for ($d=0;$d<sizeof($tds);$d++) {
							unset($td_border); unset($td_width); unset($td_height); unset($td_align); unset($td_valign); unset($td_bgcolor); unset($td_colspan); unset($td_rowspan);
							$tmp3 = split(">", $tds[$d]);

							$p_td = "";
							$perms = ereg_replace(" +", "&", @trim($tmp3[0]));
							$perms = strtolower($perms);
							parse_str($perms);
							if (isset($colspan)) { $td_colspan = $colspan; unset($colspan); $p_td .= "colspan=".$td_colspan." - "; }
							else {$td_colspan =1;}
							if (isset($rowspan)) { $td_rowspan = $rowspan; unset($rowspan); $p_td .= "rowspan=".$td_rowspan." - "; }
							else {$td_rowspan =1;}
							if (isset($border)) { $td_border = $border; unset($border); $p_td .= "border=".$td_border." - "; }
							else {$td_border = $tr_border;}
							if (isset($width)) { $td_width = $width; unset($width); $p_td .= "width=".$td_width." - "; }
							else {$td_width = "no";}
							if (isset($align)) { $td_align = $align; unset($align); $p_td .= "align=".$td_align." - "; }
							else {$td_align = $tr_align;}
							if (isset($valign)) { $td_valign = $valign; unset($valign); $p_td .= "valign=".$td_valign." - "; }
							else {$td_valign = $tr_valign;}
							if (isset($bgcolor)) { $td_bgcolor = $bgcolor; unset($bgcolor); $p_td .= "bgcolor=".$td_bgcolor." - "; }
							else {$td_bgcolor = $tr_bgcolor;}
							if (isset($color)) { $td_bgccolor = $color; unset($color);}
							else {$td_bgccolor = $tr_bgccolor;}
							$other3 = substr(strstr($tds[$d], ">"), 1);
////////////////////////// - CELLS
							switch (strtoupper($td_valign)) {
								case "TOP": $td_val_f = "\\clvertalt"; break;
								case "MIDDLE": $td_val_f = "\\clvertalc"; break;
								case "BOTTOM": $td_val_f = "\\clvertalb"; break;
							}

							if ($td_bgcolor==0 && $td_bgccolor==0) { $td_bg_f = ""; }
							else if ($td_bgccolor>0) { $td_bgcolor = $td_bgccolor; $td_bg_f = "\\clcbpat".$td_bgcolor; }
							else { $td_bgcolor = $td_bgcolor*100; $td_bg_f = "\\clcbpat8\\clshdng".$td_bgcolor; }
							unset($color);
							if ($td_border==1) {
								$td_brd_f = "\\clbrdrt\\brdrs\\brdrw10".$brd_color."\\clbrdrl\\brdrs\\brdrw10".$brd_color."\\clbrdrb\\brdrs\\brdrw10".$brd_color."\\clbrdrr\\brdrs\\brdrw10".$brd_color;
							}
							else {
								$td_brd_f = "";
								if (preg_match("/t/",$td_border)) { $td_brd_f .= "\\clbrdrt\\brdrs\\brdrw10".$brd_color; }
								if (preg_match("/b/",$td_border)) { $td_brd_f .= "\\clbrdrb\\brdrs\\brdrw10".$brd_color; }
								if (preg_match("/r/",$td_border)) { $td_brd_f .= "\\clbrdrr\\brdrs\\brdrw10".$brd_color; }
								if (preg_match("/l/",$td_border)) { $td_brd_f .= "\\clbrdrl\\brdrs\\brdrw10".$brd_color; }
							}
							if (ereg("%",$td_width)) {
								$ooo = ereg_replace("%", "", $td_width);
								$td_wdth_mass[] = round(($tr_twips_wdth / 100) * $ooo);
								$tmp_wdth = round(($tr_twips_wdth / 100) * $ooo);
							}
							else if ($td_width=="no") {$td_wdth_mass[] = "no"; $tmp_wdth = "no"; }
							else { $td_wdth_mass[] = $this->twips($td_width); $tmp_wdth = $this->twips($td_width); }

							switch (strtoupper($td_align)) {
								case "CENTER": $td_text = "\\qc ".$this->toCyr($other3,1).""; break;
								case "LEFT": $td_text = "\\ql ".$this->toCyr($other3,1).""; break;
								case "RIGHT": $td_text = "\\qr ".$this->toCyr($other3,1).""; break;
								case "JUSTIFY": $td_text = "\\qj ".$this->toCyr($other3,1).""; break;
							}

							$td_head_res[] = $td_val_f.$td_bg_f.$td_brd_f."\\cltxlrtb";
							$td_body_res .= "\\intbl {".$td_text."}\\cell \\pard \r\n";
							///////////////////////////////////

							$tmp_head = $tbl_keep_all.$td_val_f.$td_bg_f.$td_brd_f."\\cltxlrtb";
							//$tmp_body = "\\intbl {".$td_text."}\\cell \\pard \r\n";
							$tmp_body = "\\intbl ".$td_text."\\cell \\pard \r\n";

							//////////////////////////////////
							///////////////
								for ($gh=0;$gh<$td_rowspan;$gh++) {
										for ($jh=0;$jh<$td_colspan;$jh++)
										{
											$all_data[$r][$num_t][$gh][$jh] = $other3;
											$all_data_head[$r][$num_t][$gh][$jh] = $tmp_head;
											$all_data_body[$r][$num_t][$gh][$jh] = $tmp_body;
											$all_data_wdth[$r][$num_t][$gh][$jh] = $tmp_wdth;
										}
								}
							$num_t++;

							$cells_row_wdth++;
							if ($td_colspan>1) {
								$cells_row_wdth+=$td_colspan-1;
							}
							///////////////
						}
					}
					///////////////
					if ($cells_wdth<$cells_row_wdth) {$cells_wdth=$cells_row_wdth;}
					$cells_hght++;
					$cells_data = "";
					///////////////
				}

			}
///////////////
//		}
		return $this->tbl_full($all_data_head,$all_data_body,$all_data_wdth,$cells_wdth);
	}// end of function
//-------------------------------------------------------------------------------------------------
////////////////////// three dimensional array parse
//-------------------------------------------------------------------------------------------------
	function tbl_full($mass_head,$mass_body,$mass_wdth,$width) {
		$shablon_mass = array();
		$fin_tbl_head = array();
		$fin_tbl_body = array();
		$fin_tbl_wdth = array();

		$h = "\\intbl  \\cell \\pard \r\n";
		$hh = "no";
		$hhh = "\\clvertalc\\clbrdrt\\brdrs\\brdrw10 \\clbrdrl\\brdrs\\brdrw10 \\clbrdrb\\brdrs\\brdrw10 \\clbrdrr\\brdrs\\brdrw10 \\cltxlrtb";

		for ($i=0;$i<sizeof($mass_wdth);$i++) { for ($b=0;$b<$width;$b++){ $shablon_mass[$i][$b] = "&nbsp;"; $fin_tbl_head[$i][$b] = $hhh;$fin_tbl_body[$i][$b] = $h;$fin_tbl_wdth[$i][$b] = $hh; } }
		$num_id = 0;
		for ($a=0;$a<sizeof($mass_wdth);$a++)
		{
			$id = 0; // ����� ����� � ����
			for ($c=0;$c<$width;$c++) {
				if ($fin_tbl_body[$a][$c]==$h) {
						for ($lk=0;$lk<sizeof($mass_wdth[$a][$id]);$lk++) {
							for ($kl=0;$kl<sizeof($mass_wdth[$a][$id][$lk]);$kl++) {
								if ($mass_wdth[$a][$id][$lk][$kl]!="") {
									$shablon_mass[$a+$lk][$c+$kl] = $num_id+$id+1;
									$fin_tbl_head[$a+$lk][$c+$kl] = $mass_head[$a][$id][$lk][$kl];
									$fin_tbl_body[$a+$lk][$c+$kl] = $mass_body[$a][$id][$lk][$kl];
									$fin_tbl_wdth[$a+$lk][$c+$kl] = $mass_wdth[$a][$id][$lk][$kl];
								}
							}
						}
					$id++; // $num_id += $id;
				}
			}
			$num_id += $id;
		}
		$fin_max = $this->row_me($fin_tbl_wdth,$width,$shablon_mass);
		return $this->final_parce($fin_tbl_head,$fin_max,$fin_tbl_body,$shablon_mass);
	}// end of function
//-------------------------------------------------------------------------------------------------
	function final_parce($head,$fin_max,$body,$shablon) {
		$tr_all_f = "";
		for ($h=0;$h<sizeof($shablon);$h++) {
			$td_head_f = "";
			$td_body_f = "";
			$tr_res = $this->tr_hd_mass[$h];
			$iiii = 0;

			for ($w=0;$w<sizeof($shablon[0]);$w++) {
				$iiii += (isset($fin_max[$w])) ? $fin_max[$w] : 0;
				if (
						((isset($shablon[$h][$w]) && isset($shablon[$h][$w+1])) && ($shablon[$h][$w] != $shablon[$h][$w+1])) ||
						((isset($shablon[0]) && $w==sizeof($shablon[0])-1))
					) {

					$rspn="rspn".$shablon[$h][$w];
					if (@!$$rspn) {
						if (@$shablon[$h][$w] == @$shablon[$h+1][$w]) { $rs="\\clvmgf"; $$rspn=true;}
						else {$rs="";}
					}
					else {
						if ($shablon[$h][$w] != $shablon[$h-1][$w]) { $rs=""; }
						else {$rs="\\clvmrg";}
					}
					$td_head_f .= $rs.$head[$h][$w]."\\cellx".$iiii."\r\n";
					if ($rs=="\\clvmrg") {$td_body_f .= "\\intbl \\cell \\pard \r\n";}
					else {$td_body_f .= $body[$h][$w];}

				}
			}
			$tr_all_f .= $tr_res.$td_head_f."\r\n".$td_body_f."\r\n\\intbl \\row \\pard\r\n";
		}
		return $tr_all_f;
	}// end of function
//-------------------------------------------------------------------------------------------------
//////////////  object inserted tables searching
//-------------------------------------------------------------------------------------------------
	function obj_srch($shablon) {
		$width = sizeof($shablon[0]);
		$height = sizeof($shablon);
		for ($h=0;$h<$height;$h++) {
			$g_count=0;
			for ($w=0;$w<$width;$w++) {
				if ($shablon[$h][$w] != $shablon[$h+1][$w]) { $g_count++; }
			}
			$g_mass[$h] = $g_count;
		}
		for ($w=0;$w<$width;$w++) {
			$v_count=0;
			for ($h=0;$h<$height;$h++) {
				if ($shablon[$h][$w] != $shablon[$h][$w+1]) { $v_count++; }
			}
			$v_mass[$w] = $v_count;
		}
	}
//-------------------------------------------------------------------------------------------------
////////////////////// crow widths counting function
//-------------------------------------------------------------------------------------------------
	function row_me($wdth,$or_wdth,$shablon) {
		for ($h=0;$h<sizeof($wdth);$h++) {
			$count = 0; $sum = 0; $mstc = 0;
			for ($w=0;$w<$or_wdth;$w++) {
				if ($wdth[$h][$w] == "no") { $count++; }
				else {
					if ($shablon[$h][$w] != $shablon[$h][$w+1]) {
						$sum += $wdth[$h][$w];
						$wdth[$h][$w] = $wdth[$h][$w]."mst".$mstc; $mstc = 0;
					}
					else { $wdth[$h][$w] = $wdth[$h][$w]."sl".$mstc; $mstc++;}
				}
			}
			if ($count == 0) {$count = 1;}
			$opt = round(($this->tb_wdth - $sum) / $count);
			for ($w=0;$w<$or_wdth;$w++) {
				if ($wdth[$h][$w] == "no") { $wdth[$h][$w] = $opt; }
			}
		}
		for ($w=0;$w<$or_wdth;$w++) {
			$fl=false;
			for ($h=0;$h<sizeof($wdth);$h++) {
				if (ereg("mst",$wdth[$h][$w]) || ereg("sl",$wdth[$h][$w])) { $fl = true; }
			}
			if ($fl) { $yes_no[$w] = "yes"; }
			else { $yes_no[$w] = "no"; }
		}
		return $this->mxs($wdth,$or_wdth,$shablon);
	}// end of function
//-------------------------------------------------------------------------------------------------
////////////////////// main borders counting function
//-------------------------------------------------------------------------------------------------
	function mxs($wdth,$or_wdth,$shablon) {
		$t_count = 0; $mst = array();$fin_max = array();
		for ($h=0;$h<$or_wdth;$h++) { $fin_max[$h]="no"; }
		for ($w=0;$w<$or_wdth;$w++) {
			for ($h=0;$h<sizeof($wdth);$h++) {
				$d_tmp = 0;
				if (ereg("mst",$wdth[$h][$w])) {
					$width = preg_replace("/mst\d+/","",$wdth[$h][$w]);
					$span = preg_replace("/\d+mst/","",$wdth[$h][$w]);
					if ($span>0) {
						$tty = $width / ($span + 1);
						if ($mst_mass[$w]<$tty) { $mst_mass[$w] = $tty; $mst[$w] = $wdth[$h][$w]; }
					}
					else {
						$d_tmp = $width;
					}
				}
				if ($fin_max[$w]<$d_tmp || $fin_max[$w] == "no") { $fin_max[$w] = $d_tmp; }
			}
			$t_count++;
		}
		for ($i=0;$i<$t_count;$i++) { if ($fin_max[$i] == "") { $fin_max[$i] = "no"; } }
		return $this->mxs2($fin_max,$mst);
	}
//-------------------------------------------------------------------------------------------------
	function mxs2($fin_max,$mst) {
		for ($i=0;$i<sizeof($fin_max);$i++) {
			$tmp_sum = 0; $fl = 1;
			if (isset($mst[$i]) &&  $mst[$i] != "") {
				if ($fin_max[$i] == "no") {
					$width = preg_replace("/mst\d+/","",$mst[$i]);
					$span = preg_replace("/\d+mst/","",$mst[$i]);
					for ($h=$i-$span;$h<$i;$h++) {
						if ($fin_max[$h] != "no") { $tmp_sum += $fin_max[$h]; }
						else { $fl++; }
					}
					$opt = round(($width - $tmp_sum) / $fl);
					for ($h=$i-$span;$h<=$i;$h++) {
						if ($fin_max[$h] == "no") { $fin_max[$h] = $opt; }
					}
				}
				else {
					$width = preg_replace("/mst\d+/","",$mst[$i]);
					$span = preg_replace("/\d+mst/","",$mst[$i]);
					for ($h=$i-$span;$h<=$i;$h++) {
						if ($fin_max[$h] != "no") { $tmp_sum += $fin_max[$h]; }
						else { $fl++; }
					}
					$opt = round(($width - $tmp_sum) / ($fl - 1));
					if ($opt>=0) {
						for ($h=$i-$span;$h<$i;$h++) {
							if ($fin_max[$h] == "no") { $fin_max[$h] = $opt; }
						}
					}
				}
			}
		}
		$f_sum = 0; $f_fl = 0;
		for ($i=0;$i<sizeof($fin_max);$i++) {
			if ($fin_max[$i] != "no") { $f_sum += $fin_max[$i]; }
			else { $f_fl++; }
		}
		$f_fl = ($f_fl == 0) ? 1 : $f_fl;
		$f_opt = round(($this->tb_wdth - $f_sum) / $f_fl);
		if ($f_opt<0) { $f_opt = 10; }
		for ($i=0;$i<sizeof($fin_max);$i++) {
			if ($fin_max[$i] == "no") { $fin_max[$i] = $f_opt; }
		}
		return $fin_max;
	}
//////////////////////////////////////////////////////////
//-------------------------------------------------------------------------------------------------
}// end of class

?>