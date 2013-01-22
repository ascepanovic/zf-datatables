ZF-Datatables
=============
This project provides jquery datatables combined with Zend Framework 1 with
the hope to make it more easily replicate by using a configuration file.

Documentation
-------------
Currently there is no real documentation.  You'll need to see the comments inside
the source files.

Requirements
------------
Requires `Zend Framework 1 <http://framework.zend.com/>`_ library 1.8.0 or greater.

Thirdparty Software
-------------------

ZF-Datatables includes some other thirdparty software in its distribution:

public/js/DataTables-1.9.3 - jQuery Datatables. Dual licensed under the `BSD`_ and `GPL2 <http://datatables.net/license_gpl2>`_ licenses.

public/js/datatables-editable - `jQuery-datatables-editable <http://code.google.com/p/jquery-datatables-editable/>`_. Licensed under the `BSD`_ license.

public/js/jeditable - `Jeditable <http://www.appelsiini.net/projects/jeditable>`_. Licensed under the `MIT`_ license.

public/js/jvalidate - `jQuery validation`_. Dual licensed under the `MIT`_ and `GPL <http://www.opensource.org/licenses/gpl-license.php>`_ licenses.

public/js/maskedinput - `jQuery Masked Input <http://digitalbush.com/projects/masked-input-plugin/>`_. Licensed under the `MIT`_ license.

public/js/jGrowl - `jQuery jGrowl <http://stanlemon.net/pages/jgrowl>`_. Licensed under the `MIT`_ license and `GPL`_ license.


Using ZF-Datatables
-------------------
To use the example you will need to set database with the name & username of 'zf-datatables'.
You can use any password but you will need to update it in the Multi DB section of
application/configs/application.ini.  Import the users table located in sql/data.sql.
Now point your browser to the WEB_ROOT/public folder and click "Datatables Example" to see
demo.

Licensing
---------
This project is licensed under the `BSD`_ license.

ZF-Datatables URLs
------------------

* Git location:       http://github.com/jamesj2/zf-datatables/
* Home Page:          none yet

.. _MIT: http://www.opensource.org/licenses/mit-license.php
.. _MITmaskedinput: http://digitalbush.com/projects/masked-input-plugi/#license
.. _BSD: http://www.opensource.org/licenses/bsd-license.php
.. _jQuery validation: http://bassistance.de/jquery-plugins/jquery-plugin-validation
.. _GPL: http://www.gnu.org/licenses/gpl.html