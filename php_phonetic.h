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

#ifndef PHP_PHONETIC_H
#define PHP_PHONETIC_H

#include "php.h"

#define PHP_PHONETIC_VERSION "0.2.0"

extern zend_module_entry phonetic_module_entry;
#define phpext_phonetic_ptr &phonetic_module_entry

void bmpm_minit(void);
void bmpm_mshutdown(void);

void dms_minit(void);
void dms_mshutdown(void);

#ifdef PHP_WIN32
#define PHP_PHONETIC_API __declspec(dllexport)
#else
#define PHP_PHONETIC_API
#endif

#endif /* PHP_PHONETIC_H */
