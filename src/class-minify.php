<?php

namespace Cachetop;

class Minify {

	/**
	 * We'll only compress a view if none of the following conditions are met.
	 * 
	 * 1) <pre> or <textarea> tags
	 * 2) Embedded javascript (opening <script> tag not immediately followed by </script>)
	 * 3) Value attribute that contains 2 or more adjacent spaces
	 *
	 * @param  string $value
	 * 
	 * @return bool
	 */
	protected function should_minify( $value ) {
		return ! ( is_string( $value ) && (
			preg_match( '/skipmin/', $value )
         	|| preg_match( '/<(pre|textarea)/', $value )
         	|| preg_match( '/<script[^\??>]*>[^<\/script>]/', $value )
         	|| preg_match( '/value=("|\')(.*)([ ]{2,})(.*)("|\')/', $value )
        ) );
	}

	/**
	 * Compress html.
	 *
	 * @param  string $value
	 *
	 * @return string
	 */
	public function compress( $value ) {
		if ( $this->should_minify( $value ) ) {
			$replace = array(
				'/<!--(?!\s*(?:\[if [^\]]+]|<!|>))(?:(?!-->).)*-->/s' => '',
                "/<\?php/"                                            => '<?php ',
                "/\n([\S])/"                                          => ' $1',
                "/\r/"                                                => '',
                "/\n/"                                                => '',
                "/\t/"                                                => ' ',
                "/ +/"                                                => ' ',
                '/\[(google[\w:\s]+)\]/'                              => '<!--$1-->'
            );

            return preg_replace( array_keys( $replace ), array_values( $replace ), $value );
		}

		return $value;
	}
}
