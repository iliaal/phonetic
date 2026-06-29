/* This is a generated file, edit phonetic.stub.php instead.
 * Stub hash: d3207dbb87e6f1d888324b3f1b64e7b03fe25724 */

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_double_metaphone, 0, 1, IS_ARRAY, 0)
	ZEND_ARG_TYPE_INFO(0, string, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, max_length, IS_LONG, 0, "4")
ZEND_END_ARG_INFO()

ZEND_FUNCTION(double_metaphone);

static const zend_function_entry ext_functions[] = {
	ZEND_FE(double_metaphone, arginfo_double_metaphone)
	ZEND_FE_END
};
