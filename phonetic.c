/*
  +----------------------------------------------------------------------+
  | Copyright (c) 2026, Ilia Alshanetsky                                 |
  | Copyright (c) 2026, Advanced Internet Designs Inc.                   |
  +----------------------------------------------------------------------+
  | This source file is subject to the BSD 3-Clause license that is      |
  | bundled with this package in the file LICENSE.                       |
  +----------------------------------------------------------------------+
  | Author: Ilia Alshanetsky <ilia@ilia.ws>                              |
  +----------------------------------------------------------------------+
*/

#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include "php.h"
#include "ext/standard/info.h"
#include "php_phonetic.h"

/* Function table is empty in the scaffold; double_metaphone(), bmpm() and
 * dm_soundex() are registered through the stub-driven arginfo as each lands. */
static const zend_function_entry phonetic_functions[] = {
	PHP_FE_END
};

PHP_MINIT_FUNCTION(phonetic)
{
	/* BMPM_* name-type and accuracy constants are registered here once the
	 * bmpm() engine lands. */
	return SUCCESS;
}

PHP_MINFO_FUNCTION(phonetic)
{
	php_info_print_table_start();
	php_info_print_table_header(2, "phonetic support", "enabled");
	php_info_print_table_row(2, "Version", PHP_PHONETIC_VERSION);
	php_info_print_table_end();
}

zend_module_entry phonetic_module_entry = {
	STANDARD_MODULE_HEADER,
	"phonetic",
	phonetic_functions,
	PHP_MINIT(phonetic),
	NULL,
	NULL,
	NULL,
	PHP_MINFO(phonetic),
	PHP_PHONETIC_VERSION,
	STANDARD_MODULE_PROPERTIES
};

#ifdef COMPILE_DL_PHONETIC
#ifdef ZTS
ZEND_TSRMLS_CACHE_DEFINE()
#endif
ZEND_GET_MODULE(phonetic)
#endif
