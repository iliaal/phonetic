/* This is a generated file, edit phonetic.stub.php instead.
 * Stub hash: 6ce1d9d272bcbc6bc99d209bdfac6c02972a209f */

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

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_dm_soundex, 0, 1, IS_ARRAY, 0)
	ZEND_ARG_TYPE_INFO(0, string, IS_STRING, 0)
ZEND_END_ARG_INFO()

ZEND_FUNCTION(double_metaphone);
ZEND_FUNCTION(bmpm);
ZEND_FUNCTION(dm_soundex);

static const zend_function_entry ext_functions[] = {
	ZEND_FE(double_metaphone, arginfo_double_metaphone)
	ZEND_FE(bmpm, arginfo_bmpm)
	ZEND_FE(dm_soundex, arginfo_dm_soundex)
	ZEND_FE_END
};

static void register_phonetic_symbols(int module_number)
{
	REGISTER_LONG_CONSTANT("BMPM_GENERIC", 0, CONST_PERSISTENT);
	REGISTER_LONG_CONSTANT("BMPM_ASHKENAZI", 1, CONST_PERSISTENT);
	REGISTER_LONG_CONSTANT("BMPM_SEPHARDIC", 2, CONST_PERSISTENT);
	REGISTER_LONG_CONSTANT("BMPM_APPROX", 1, CONST_PERSISTENT);
	REGISTER_LONG_CONSTANT("BMPM_EXACT", 2, CONST_PERSISTENT);
}
