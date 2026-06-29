/* This is a generated file, edit phonetic.stub.php instead.
 * Stub hash: 0cf29e9fc875c88f6a0de3f086a8a99120c04d83 */

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_double_metaphone, 0, 1, IS_ARRAY, 0)
	ZEND_ARG_TYPE_INFO(0, string, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, max_length, IS_LONG, 0, "4")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_bmpm, 0, 1, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO(0, string, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, name_type, IS_LONG, 0, "BMPM_GENERIC")
	ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, accuracy, IS_LONG, 0, "BMPM_APPROX")
	ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, language, IS_STRING, 0, "\"\"")
ZEND_END_ARG_INFO()

ZEND_FUNCTION(double_metaphone);
ZEND_FUNCTION(bmpm);

static const zend_function_entry ext_functions[] = {
	ZEND_FE(double_metaphone, arginfo_double_metaphone)
	ZEND_FE(bmpm, arginfo_bmpm)
	ZEND_FE_END
};
