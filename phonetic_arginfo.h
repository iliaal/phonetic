/* This is a generated file, edit phonetic.stub.php instead.
 * Stub hash: 240abf71b5d7f4afa00182e305ed21116ed779d4 */

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

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_nysiis, 0, 1, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO(0, string, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, max_length, IS_LONG, 0, "6")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_match_rating, 0, 1, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO(0, string, IS_STRING, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_nysiis_match, 0, 2, _IS_BOOL, 0)
	ZEND_ARG_TYPE_INFO(0, string1, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO(0, string2, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, max_length, IS_LONG, 0, "6")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_match_rating_compare, 0, 2, _IS_BOOL, 0)
	ZEND_ARG_TYPE_INFO(0, string1, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO(0, string2, IS_STRING, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_double_metaphone_match, 0, 2, IS_LONG, 0)
	ZEND_ARG_TYPE_INFO(0, string1, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO(0, string2, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, max_length, IS_LONG, 0, "4")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_bmpm_match, 0, 2, _IS_BOOL, 0)
	ZEND_ARG_TYPE_INFO(0, string1, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO(0, string2, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, name_type, IS_LONG, 0, "BMPM_GENERIC")
	ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, accuracy, IS_LONG, 0, "BMPM_APPROX")
	ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, language, IS_STRING, 0, "\"\"")
ZEND_END_ARG_INFO()

#define arginfo_dm_soundex_match arginfo_match_rating_compare

ZEND_FUNCTION(double_metaphone);
ZEND_FUNCTION(bmpm);
ZEND_FUNCTION(dm_soundex);
ZEND_FUNCTION(nysiis);
ZEND_FUNCTION(match_rating);
ZEND_FUNCTION(nysiis_match);
ZEND_FUNCTION(match_rating_compare);
ZEND_FUNCTION(double_metaphone_match);
ZEND_FUNCTION(bmpm_match);
ZEND_FUNCTION(dm_soundex_match);

static const zend_function_entry ext_functions[] = {
	ZEND_FE(double_metaphone, arginfo_double_metaphone)
	ZEND_FE(bmpm, arginfo_bmpm)
	ZEND_FE(dm_soundex, arginfo_dm_soundex)
	ZEND_FE(nysiis, arginfo_nysiis)
	ZEND_FE(match_rating, arginfo_match_rating)
	ZEND_FE(nysiis_match, arginfo_nysiis_match)
	ZEND_FE(match_rating_compare, arginfo_match_rating_compare)
	ZEND_FE(double_metaphone_match, arginfo_double_metaphone_match)
	ZEND_FE(bmpm_match, arginfo_bmpm_match)
	ZEND_FE(dm_soundex_match, arginfo_dm_soundex_match)
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
