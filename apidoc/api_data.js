define({ api: [
  {
    "type": "get",
    "url": "/map/{hash}/data/{order}/complete.json",
    "title": "        autocomplete",
    "name": "completeSpatialDataAction",
    "group": "MapData",
    "description": "<p>get available spatialNames/Codes for this userData</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "string",
            "field": "q",
            "optional": false,
            "description": "<p>search term, at least 2 characters</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "src/R3gis/AppBundle/Controller/Api/MapController.php"
  },
  {
    "type": "get",
    "url": "/map/{hash}/data/{order}/data.json",
    "title": "        getUserData",
    "name": "getTableUserDataAction",
    "group": "MapData",
    "description": "<p>Get data from table</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "string",
            "field": "spatialMatch",
            "optional": true,
            "description": "<p>if is &#39;false&#39;, limit the results to data where the spatialName does not match, if is &#39;true&#39; only returns matching data.</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "src/R3gis/AppBundle/Controller/Api/MapController.php"
  },
  {
    "type": "put",
    "url": "/map/{hash}/data/{order}/data.json",
    "title": "        updateUserData",
    "name": "updateTableUserDataAction",
    "group": "MapData",
    "description": "<p>set elements from data table</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "json",
            "field": "updateField",
            "optional": false,
            "description": "<p>json must be an array, even if it only contains one element.</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "src/R3gis/AppBundle/Controller/Api/MapController.php"
  },
  {
    "type": "post",
    "url": "/map",
    "title": "        Add map",
    "name": "addAction",
    "group": "Map",
    "description": "<p>Add a new empty map with default values and no layer. Return the new-created map data</p>",
    "version": "0.0.0",
    "filename": "src/R3gis/AppBundle/Controller/Api/MapController.php"
  },
  {
    "type": "post",
    "url": "/map/{hash}",
    "title": "        Copy map",
    "name": "copyAction",
    "group": "Map",
    "description": "<p>Copy the map with the given hash to a new one. Return the map-info data of the new map                        NB: The user.data tables are duplicated</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "boolean",
            "field": "temporary",
            "optional": true,
            "description": "<p>if &quot;true&quot; the new created map is a temporary map</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "src/R3gis/AppBundle/Controller/Api/MapController.php"
  },
  {
    "type": "delete",
    "url": "/map/{hash}",
    "title": "        Delete map",
    "name": "delAction",
    "group": "Map",
    "description": "<p>Remove the given map and cascade all the related temporary maps                 and the related user.data tables.</p>",
    "version": "0.0.0",
    "filename": "src/R3gis/AppBundle/Controller/Api/MapController.php"
  },
  {
    "type": "get",
    "url": "/map/extent.json",
    "title": "        Map extent",
    "name": "getExtentAction",
    "group": "Map",
    "description": "<p>Return the default map extent</p>",
    "version": "0.0.0",
    "filename": "src/R3gis/AppBundle/Controller/Api/MapController.php"
  },
  {
    "type": "get",
    "url": "/map/{hash}/map.json",
    "title": " Map info",
    "name": "infoAction",
    "group": "Map",
    "description": "<p>Return the detail map info for the given hash. See map response json for detail</p>",
    "version": "0.0.0",
    "filename": "src/R3gis/AppBundle/Controller/Api/MapController.php"
  },
  {
    "type": "get",
    "url": "/map/maps.json",
    "title": " Map list",
    "name": "listAction",
    "group": "Map",
    "description": "<p>Return the list of the public maps and                 the authenticated user&#39;s maps.</p>",
    "parameter": {
      "fields": {
        "Filters": [
          {
            "group": "Filters",
            "type": "String",
            "field": "q",
            "optional": true,
            "description": "<p>Filter the result on map&#39;s name or description</p>"
          },
          {
            "group": "Filters",
            "type": "String",
            "field": "language",
            "optional": true,
            "description": "<p>Filter result for the given language.                                          Valid language are en=english, de=german, it=italian                                         Multiple space-separated values allowed</p>"
          },
          {
            "group": "Filters",
            "type": "boolean",
            "field": "onlyMine",
            "optional": true,
            "description": "<p>if &quot;true&quot; return only the maps of the current user</p>"
          }
        ],
        "Order/Limit": [
          {
            "group": "Order/Limit",
            "type": "string",
            "field": "order",
            "optional": true,
            "description": "<p>Order of the resultset.                                               Accepted values: &quot;recent&quot; (more recent) and &quot;click&quot; (more clicked maps)                                              Multiple space-separated values allowed</p>"
          },
          {
            "group": "Order/Limit",
            "type": "integer",
            "field": "limit",
            "defaultValue": "50",
            "optional": true,
            "description": "<p>Limit the number of results</p>"
          },
          {
            "group": "Order/Limit",
            "type": "integer",
            "field": "offset",
            "optional": true,
            "description": "<p>Return the result starting from 0-based offset</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "src/R3gis/AppBundle/Controller/Api/MapController.php"
  },
  {
    "type": "put",
    "url": "/map/{hash}",
    "title": "        Modify map",
    "name": "modAction",
    "group": "Map",
    "description": "<p>Update the given map or optionally store changes to a new temporary map.                Input data is the map-info data. Return the map data (old or new depende on duplicate parameter).                Temporary maps are deleted after 24 hours</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "boolean",
            "field": "duplicate",
            "optional": true,
            "description": "<p>if &quot;true&quot; save the changes to a new temporary map. Old map is NOT changed.                                     NB: The user.data tables are NON duplicated                                     This parameter must be set on the url</p>"
          },
          {
            "group": "Parameter",
            "type": "boolean",
            "field": "purge",
            "optional": true,
            "description": "<p>if &quot;true&quot; remove all temporary maps based on the given hash (cascade).                                     Final saving. &quot;duplicate&quot; parameter must be false.                                     This parameter must be set on the url</p>"
          },
          {
            "group": "Parameter",
            "type": "string",
            "field": "copyFromHash",
            "optional": true,
            "description": "<p>if given, replace the original data with the data of the map with this hash,                                      then apply the request update (on the url hash).                                     &quot;duplicate&quot; parameter must be false                                     This parameter must be set on the body</p>"
          },
          {
            "group": "Parameter",
            "type": "json",
            "field": "map",
            "optional": true,
            "description": "<p>the data in map-info json format to store                                     This parameter must be set on the body</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "src/R3gis/AppBundle/Controller/Api/MapController.php"
  },
  {
    "type": "get",
    "url": "/map/{hash}/stat/{order}/info.json",
    "title": "        Map statistic info",
    "name": "statInfoAction",
    "group": "Map",
    "description": "<p>Return the statistic info of the map. Resturn a list of data (actualy max 1 row)</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "float",
            "field": "x",
            "optional": false,
            "description": "<p>the longitude (in map coordinate)</p>"
          },
          {
            "group": "Parameter",
            "type": "float",
            "field": "y",
            "optional": false,
            "description": "<p>the latitude (in map coordinate)</p>"
          },
          {
            "group": "Parameter",
            "type": "float",
            "field": "buffer",
            "optional": true,
            "description": "<p>(not implemented) the buffer to apply to the point to find the data</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "src/R3gis/AppBundle/Controller/Api/MapController.php"
  },
  {
    "type": "PUT",
    "url": "/resetpassword/reset/{hash}",
    "title": " change Password",
    "name": "changePasswordAction",
    "group": "ResetPassword",
    "description": "<p>Returns json with success=true, if hash and password are valid and password was changed.</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "String",
            "field": "hash",
            "optional": false,
            "description": "<p>reset password-hash</p>"
          },
          {
            "group": "Parameter",
            "type": "String",
            "field": "password",
            "optional": false,
            "description": "<p>the new password</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "src/R3gis/AppBundle/Controller/Api/ResetPasswordController.php"
  },
  {
    "type": "POST",
    "url": "/resetpassword/request",
    "title": " request Email",
    "name": "requestAction",
    "group": "ResetPassword",
    "description": "<p>Request email for Password reset.                 Returns json with success=true, if user with that email exists and email was sent.</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "String",
            "field": "email",
            "optional": false,
            "description": "<p>emailaddress of user</p>"
          },
          {
            "group": "Parameter",
            "type": "String",
            "field": "captcha",
            "optional": false,
            "description": "<p>captcha</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "src/R3gis/AppBundle/Controller/Api/ResetPasswordController.php"
  },
  {
    "type": "get",
    "url": "/resetpassword/reset/{hash}",
    "title": " reset link",
    "name": "resetLinkAction",
    "group": "ResetPassword",
    "description": "<p>Link the client gets in his email. Will redirect to angular resetpw/error page, depending if hash is valid or not.</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "String",
            "field": "hash",
            "optional": false,
            "description": "<p>reset password-hash</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "src/R3gis/AppBundle/Controller/Api/ResetPasswordController.php"
  },
  {
    "type": "GET",
    "url": "/captcha/request.json",
    "title": " request captcha",
    "name": "getCaptchaAction",
    "group": "captcha",
    "description": "<p>Request captcha image                Returns json with success=true and the image in result as base64 image.</p>",
    "version": "0.0.0",
    "filename": "src/R3gis/AppBundle/Controller/Api/CaptchaController.php"
  },
  {
    "type": "DELETE",
    "url": "/user/{id}/",
    "title": " Delete user",
    "name": "deleteAction",
    "group": "user",
    "description": "<p>Returns json with success=true, when user successfully deleted</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "Integer",
            "field": "id",
            "optional": false,
            "description": "<p>userid</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "src/R3gis/AppBundle/Controller/Api/UserController.php"
  },
  {
    "type": "GET",
    "url": "/user/users.json/",
    "title": " List users",
    "name": "listAction",
    "group": "user",
    "description": "<p>Returns list of users as json, but only to admins</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "Integer",
            "field": "id",
            "optional": false,
            "description": "<p>userid</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "src/R3gis/AppBundle/Controller/Api/UserController.php"
  },
  {
    "type": "post",
    "url": "/user/login/",
    "title": " login user",
    "name": "loginAction",
    "group": "user",
    "description": "<p>Returns json with success=true and object user with user infos, when successful</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "String",
            "field": "email",
            "optional": false,
            "description": "<p>email</p>"
          },
          {
            "group": "Parameter",
            "type": "String",
            "field": "password",
            "optional": false,
            "description": "<p>password</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "src/R3gis/AppBundle/Controller/Api/UserController.php"
  },
  {
    "type": "get",
    "url": "/user/logout/",
    "title": " logout user",
    "name": "logoutAction",
    "group": "user",
    "description": "<p>Returns json with success=true, when successful</p>",
    "version": "0.0.0",
    "filename": "src/R3gis/AppBundle/Controller/Api/UserController.php"
  },
  {
    "type": "PUT",
    "url": "/user/{id}/",
    "title": " Modify user",
    "name": "modifyAction",
    "group": "user",
    "description": "<p>Returns json with success=true, when user successfully modified</p>",
    "parameter": {
      "fields": {
        "Parameter": [
          {
            "group": "Parameter",
            "type": "object",
            "field": "user",
            "optional": false,
            "description": "<p>json user object with properties to be changed</p>"
          }
        ]
      }
    },
    "version": "0.0.0",
    "filename": "src/R3gis/AppBundle/Controller/Api/UserController.php"
  }
] });