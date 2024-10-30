(function (context) {
    function ApiProxy() {
        this.channelIdWsNotifyChannel = {};
    }

    // Make ApiProxy to inherit from EventEmitter
    heir.inherit(ApiProxy, EventEmitter, true);

    ApiProxy.prototype.logMessage = function (message, options, callback) {
        if (typeof options === 'function') {
            callback = options;
            options = undefined;
        }

        callApiMethod('logMessage', [message, options], callback);
    };

    ApiProxy.prototype.sendMessage = function (message, targetDevice, options, callback) {
        if (typeof options === 'function') {
            callback = options;
            options = undefined;
        }

        callApiMethod('sendMessage', [message, targetDevice, options], callback);
    };

    ApiProxy.prototype.readMessage = function (messageId, options, callback) {
        if (typeof options === 'function') {
            callback = options;
            options = undefined;
        }

        callApiMethod('readMessage', [messageId, options], callback);
    };

    ApiProxy.prototype.retrieveMessageContainer = function (messageId, callback) {
        callApiMethod('retrieveMessageContainer', [messageId], callback);
    };

    ApiProxy.prototype.retrieveMessageOrigin = function (messageId, msgToSign, callback) {
        if (typeof msgToSign === 'function') {
            callback = msgToSign;
            msgToSign = undefined;
        }

        callApiMethod('retrieveMessageOrigin', [messageId, msgToSign], callback);
    };

    ApiProxy.prototype.retrieveMessageProgress = function (messageId, callback) {
        callApiMethod('retrieveMessageProgress', [messageId], callback);
    };

    ApiProxy.prototype.listMessages = function (selector, limit, skip, callback) {
        if (typeof selector === 'function') {
            callback = selector;
            selector = undefined;
            limit = undefined;
            skip = undefined;
        }
        else if (typeof limit === 'function') {
            callback = limit;
            limit = undefined;
            skip = undefined;
        }
        else if (typeof skip === 'function') {
            callback = skip;
            skip = undefined;
        }

        callApiMethod('listMessages', [selector, limit, skip], callback);
    };

    ApiProxy.prototype.listPermissionEvents = function (callback) {
        callApiMethod('listPermissionEvents', null, callback);
    };

    ApiProxy.prototype.retrievePermissionRights = function (eventName, callback) {
        callApiMethod('retrievePermissionRights', [eventName], callback);
    };

    ApiProxy.prototype.setPermissionRights = function (eventName, rights, callback) {
        callApiMethod('setPermissionRights', [eventName, rights], callback);
    };

    ApiProxy.prototype.checkEffectivePermissionRight = function (eventName, deviceId, isProdUniqueId, callback) {
        if (typeof isProdUniqueId === 'function') {
            callback = isProdUniqueId;
            isProdUniqueId = undefined;
        }

        callApiMethod('checkEffectivePermissionRight', [eventName, deviceId, isProdUniqueId], callback);
    };

    ApiProxy.prototype.listNotificationEvents = function (callback) {
        callApiMethod('listNotificationEvents', null, callback);
    };

    ApiProxy.prototype.retrieveDeviceIdentificationInfo = function (deviceId, isProdUniqueId, callback) {
        if (typeof isProdUniqueId === 'function') {
            callback = isProdUniqueId;
            isProdUniqueId = undefined;
        }

        callApiMethod('retrieveDeviceIdentificationInfo', [deviceId, isProdUniqueId], callback);
    };

    ApiProxy.prototype.issueAsset = function (assetInfo, amount, holdingDevice, callback) {
        if (typeof holdingDevice === 'function') {
            callback = holdingDevice;
            holdingDevice = undefined;
        }

        callApiMethod('issueAsset', [assetInfo, amount, holdingDevice], callback);
    };

    ApiProxy.prototype.reissueAsset = function (assetId, amount, holdingDevice, callback) {
        if (typeof holdingDevice === 'function') {
            callback = holdingDevice;
            holdingDevice = undefined;
        }

        callApiMethod('reissueAsset', [assetId, amount, holdingDevice], callback);
    };

    ApiProxy.prototype.transferAsset = function (assetId, amount, receivingDevice, callback) {
        callApiMethod('transferAsset', [assetId, amount, receivingDevice], callback);
    };

    ApiProxy.prototype.retrieveAssetInfo = function (assetId, callback) {
        callApiMethod('retrieveAssetInfo', [assetId], callback);
    };

    ApiProxy.prototype.getAssetBalance = function (assetId, callback) {
        callApiMethod('getAssetBalance', [assetId], callback);
    };

    ApiProxy.prototype.listOwnedAssets = function (limit, skip, callback) {
        if (typeof limit === 'function') {
            callback = limit;
            limit = undefined;
            skip = undefined;
        }
        else if (typeof skip === 'function') {
            callback = skip;
            skip = undefined;
        }

        callApiMethod('listOwnedAssets', [limit, skip], callback);
    };

    ApiProxy.prototype.listIssuedAssets = function (limit, skip, callback) {
        if (typeof limit === 'function') {
            callback = limit;
            limit = undefined;
            skip = undefined;
        }
        else if (typeof skip === 'function') {
            callback = skip;
            skip = undefined;
        }

        callApiMethod('listIssuedAssets', [limit, skip], callback);
    };

    ApiProxy.prototype.retrieveAssetIssuanceHistory = function (assetId, startDate, endDate, limit, skip, callback) {
        if (typeof startDate === 'function') {
            callback = startDate;
            startDate = undefined;
            endDate = undefined;
            limit = undefined;
            skip = undefined;
        }
        else if (typeof endDate === 'function') {
            callback = endDate;
            endDate = undefined;
            limit = undefined;
            skip = undefined;
        }
        else if (typeof limit === 'function') {
            callback = limit;
            limit = undefined;
            skip = undefined;
        }
        else if (typeof skip === 'function') {
            callback = skip;
            skip = undefined;
        }

        callApiMethod('retrieveAssetIssuanceHistory', [assetId, startDate, endDate, limit, skip], callback);
    };

    ApiProxy.prototype.listAssetHolders = function (assetId, limit, skip, callback) {
        if (typeof limit === 'function') {
            callback = limit;
            limit = undefined;
            skip = undefined;
        }
        else if (typeof skip === 'function') {
            callback = skip;
            skip = undefined;
        }

        callApiMethod('listAssetHolders', [assetId, limit, skip], callback);
    };

    ApiProxy.prototype.exportAsset = function (assetId, foreignBlockchain, token, options, callback) {
        if (typeof options === 'function') {
            callback = options;
            options = undefined;
        }

        callApiMethod('exportAsset', [assetId, foreignBlockchain, token, options], callback);
    };

    ApiProxy.prototype.migrateAsset = function (assetId, foreignBlockchain, migration, options, callback) {
        if (typeof options === 'function') {
            callback = options;
            options = undefined;
        }

        callApiMethod('migrateAsset', [assetId, foreignBlockchain, migration, options], callback);
    };

    ApiProxy.prototype.assetExportOutcome = function (assetId, foreignBlockchain, callback) {
        callApiMethod('assetExportOutcome', [assetId, foreignBlockchain], callback);
    };

    ApiProxy.prototype.assetMigrationOutcome = function (migrationId, callback) {
        callApiMethod('assetMigrationOutcome', [migrationId], callback);
    };

    ApiProxy.prototype.listExportedAssets = function (selector, limit, skip, callback) {
        if (typeof selector === 'function') {
            callback = selector;
            selector = undefined;
            limit = undefined;
            skip = undefined;
        }
        else if (typeof limit === 'function') {
            callback = limit;
            limit = undefined;
            skip = undefined;
        }
        else if (typeof skip === 'function') {
            callback = skip;
            skip = undefined;
        }

        callApiMethod('listExportedAssets', [selector, limit, skip], callback);
    };

    ApiProxy.prototype.listAssetMigrations = function (selector, limit, skip, callback) {
        if (typeof selector === 'function') {
            callback = selector;
            selector = undefined;
            limit = undefined;
            skip = undefined;
        }
        else if (typeof limit === 'function') {
            callback = limit;
            limit = undefined;
            skip = undefined;
        }
        else if (typeof skip === 'function') {
            callback = skip;
            skip = undefined;
        }

        callApiMethod('listAssetMigrations', [selector, limit, skip], callback);
    };

    ApiProxy.prototype.issueNonFungibleAsset = function (issuanceInfoOrContinuationToken, nonFungibleTokens, isFinal, callback) {
        if (typeof nonFungibleTokens === 'function') {
            callback = nonFungibleTokens;
            nonFungibleTokens = undefined;
            isFinal = undefined;
        }
        else if (typeof isFinal === 'function') {
            callback = isFinal;
            isFinal = undefined;
        }

        callApiMethod('issueNonFungibleAsset', [issuanceInfoOrContinuationToken, nonFungibleTokens, isFinal], callback);
    }

    ApiProxy.prototype.reissueNonFungibleAsset = function (assetId, issuanceInfoOrContinuationToken, nonFungibleTokens, isFinal, callback) {
        if (Array.isArray(issuanceInfoOrContinuationToken)) {
            callback = isFinal;
            isFinal = nonFungibleTokens;
            nonFungibleTokens = issuanceInfoOrContinuationToken;
            issuanceInfoOrContinuationToken = undefined;
        }

        if (typeof nonFungibleTokens === 'function') {
            callback = nonFungibleTokens;
            nonFungibleTokens = undefined;
            isFinal = undefined;
        }
        else if (typeof isFinal === 'function') {
            callback = isFinal;
            isFinal = undefined;
        }

        callApiMethod('reissueNonFungibleAsset', [assetId, issuanceInfoOrContinuationToken, nonFungibleTokens, isFinal], callback);
    }

    ApiProxy.prototype.retrieveNonFungibleAssetIssuanceProgress = function (issuanceId, callback) {
        callApiMethod('retrieveNonFungibleAssetIssuanceProgress', [issuanceId], callback);
    }

    ApiProxy.prototype.retrieveNonFungibleToken = function (tokenId, options, callback) {
        if (typeof options === 'function') {
            callback = options;
            options = undefined;
        }

        callApiMethod('retrieveNonFungibleToken', [tokenId, options], callback);
    }

    ApiProxy.prototype.retrieveNonFungibleTokenRetrievalProgress = function (tokenId, retrievalId, callback) {
        callApiMethod('retrieveNonFungibleTokenRetrievalProgress', [tokenId, retrievalId], callback);
    }

    ApiProxy.prototype.transferNonFungibleToken = function (tokenId, receivingDevice, asyncProc, callback) {
        if (typeof asyncProc === 'function') {
            callback = asyncProc;
            asyncProc = undefined;
        }

        callApiMethod('transferNonFungibleToken', [tokenId, receivingDevice, asyncProc], callback);
    }

    ApiProxy.prototype.retrieveNonFungibleTokenTransferProgress = function (tokenId, transferId, callback) {
        callApiMethod('retrieveNonFungibleTokenTransferProgress', [tokenId, transferId], callback);
    }

    ApiProxy.prototype.createWsNotifyChannel = function (eventName) {
        return new WsNotifyChannel(this, eventName);
    };

    ApiProxy.prototype._setWsNotifyChannel = function (wsNotifyChannel) {
        if (Object.keys(this.channelIdWsNotifyChannel).length === 0) {
            // No notification channels previously open. Start polling server
            startPollingServer();
        }

        this.channelIdWsNotifyChannel[wsNotifyChannel.channelId] = wsNotifyChannel;
    }

    ApiProxy.prototype._clearWsNotifyChannel = function (wsNotifyChannel) {
        delete this.channelIdWsNotifyChannel[wsNotifyChannel.channelId];

        if (Object.keys(this.channelIdWsNotifyChannel).length === 0) {
            // No more notification channels open. Stop polling server
            stopPollingServer();
        }
    }

    ApiProxy.prototype._getWsNotifyChannel = function (channelId) {
        return this.channelIdWsNotifyChannel[channelId];
    }

    ApiProxy.prototype._closeAllNotifyChannels = function () {
        var _self = this;

        Object.keys(this.channelIdWsNotifyChannel).forEach(function (channelId) {
            var wsNotifyChannel = _self.channelIdWsNotifyChannel[channelId];

            wsNotifyChannel.close(function () {});

            _self._clearWsNotifyChannel(wsNotifyChannel);
        });
    }

    function callApiMethod(methodName, methodParams, cb) {
        jQuery.post(context.ctn_api_proxy_obj.ajax_url, {
            _ajax_nonce: context.ctn_api_proxy_obj.nonce,
            action: "ctn_call_api_method",
            post_id: context.ctn_api_proxy_obj.post_id,
            client_uid: context.ctn_api_proxy_obj.client_uid,
            method_name: methodName,
            method_params: JSON.stringify(methodParams || [])
        }, function (data) {
            // Success
            cb(undefined, data.data);
        }, 'json')
        .fail(function (jqXHR, textStatus, errorThrown) {
            // Failure
            var errMessage;

            console.log('JSON response:', jqXHR.responseJSON);
            console.log('Error thrown:', errorThrown);

            if (jqXHR.status >= 100) {
                errMessage = '[' + jqXHR.status + '] - ' + (typeof jqXHR.responseJSON === 'object' && jqXHR.responseJSON !== null && jqXHR.responseJSON.data
                    ? (typeof jqXHR.responseJSON.data === 'string' ? jqXHR.responseJSON.data : (typeof jqXHR.responseJSON.data === 'object'
                    && jqXHR.responseJSON.data !== null && typeof jqXHR.responseJSON.data.message === 'string' ? jqXHR.responseJSON.data.message : jqXHR.statusText))
                    : jqXHR.statusText);
            } else {
                errMessage = 'Ajax client error' + (textStatus ? ' (' + textStatus + ')' : '') + (errorThrown ? ': ' + errorThrown : '');
            }

            // Display returned error
            console.log(errMessage);
            cb(new Error(errMessage));
        });
    }

    function WsNotifyChannel(apiProxy, eventName) {
        this.apiProxy = apiProxy;
        this.eventName = eventName;
        this.channelId = random(12);
    }

    // Make WsNotifyChannel to inherit from EventEmitter
    heir.inherit(WsNotifyChannel, EventEmitter, true);

    WsNotifyChannel.prototype.open = function (callback) {
        var _self = this;

        // Make sure that notification channel for this instance is not yet open
        if (!this.apiProxy._getWsNotifyChannel(this.channelId)) {
            jQuery.post(context.ctn_api_proxy_obj.ajax_url, {
                _ajax_nonce: context.ctn_api_proxy_obj.nonce,
                action: "ctn_open_notify_channel",
                post_id: context.ctn_api_proxy_obj.post_id,
                client_uid: context.ctn_api_proxy_obj.client_uid,
                channel_id: this.channelId,
                event_name: this.eventName
            }, function (data) {
                // Success. Save notification channel instance and return
                _self.apiProxy._setWsNotifyChannel(_self);
                callback.call(_self);
            }, 'json')
            .fail(function (jqXHR, textStatus, errorThrown) {
                // Failure
                var errMessage;

                console.log('JSON response:', jqXHR.responseJSON);
                console.log('Error thrown:', errorThrown);

                if (jqXHR.status >= 100) {
                    errMessage = '[' + jqXHR.status + '] - ' + (typeof jqXHR.responseJSON === 'object' && jqXHR.responseJSON !== null && jqXHR.responseJSON.data
                            && typeof jqXHR.responseJSON.data === 'string' ? jqXHR.responseJSON.data : jqXHR.statusText);
                } else {
                    errMessage = 'Ajax client error' + (textStatus ? ' (' + textStatus + ')' : '') + (errorThrown ? ': ' + errorThrown : '');
                }

                // Display returned error
                console.log(errMessage);
                callback.call(_self, new Error(errMessage));
            });
        }
    }

    WsNotifyChannel.prototype.close = function (callback) {
        var _self = this;

        jQuery.post(context.ctn_api_proxy_obj.ajax_url, {
            _ajax_nonce: context.ctn_api_proxy_obj.nonce,
            action: "ctn_close_notify_channel",
            client_uid: context.ctn_api_proxy_obj.client_uid,
            channel_id: this.channelId
        }, function (data) {
            // Success
            callback.call(_self);
        }, 'json')
        .fail(function (jqXHR, textStatus, errorThrown) {
            // Failure
            var errMessage;

            console.log('JSON response:', jqXHR.responseJSON);
            console.log('Error thrown:', errorThrown);

            if (jqXHR.status >= 100) {
                errMessage = '[' + jqXHR.status + '] - ' + (typeof jqXHR.responseJSON === 'object' && jqXHR.responseJSON !== null && jqXHR.responseJSON.data
                    && typeof jqXHR.responseJSON.data === 'string' ? jqXHR.responseJSON.data : jqXHR.statusText);
            } else {
                errMessage = 'Ajax client error' + (textStatus ? ' (' + textStatus + ')' : '') + (errorThrown ? ': ' + errorThrown : '');
            }

            // Display returned error
            console.log(errMessage);
            callback.call(_self, new Error(errMessage));
        });
    }

    function pollServer(cb) {
        jQuery.post(context.ctn_api_proxy_obj.ajax_url, {
            _ajax_nonce: context.ctn_api_proxy_obj.nonce,
            action: "ctn_poll_server",
            client_uid: context.ctn_api_proxy_obj.client_uid
        }, function (data) {
            // Success
            cb(undefined, data.data);
        }, 'json')
        .fail(function (jqXHR, textStatus, errorThrown) {
            // Failure
            var errMessage;

            console.log('JSON response:', jqXHR.responseJSON);
            console.log('Error thrown:', errorThrown);

            if (jqXHR.status >= 100) {
                errMessage = '[' + jqXHR.status + '] - ' + (typeof jqXHR.responseJSON === 'object' && jqXHR.responseJSON !== null && jqXHR.responseJSON.data
                    ? (typeof jqXHR.responseJSON.data === 'string' ? jqXHR.responseJSON.data : (typeof jqXHR.responseJSON.data === 'object'
                    && jqXHR.responseJSON.data !== null && typeof jqXHR.responseJSON.data.message === 'string' ? jqXHR.responseJSON.data.message : jqXHR.statusText))
                    : jqXHR.statusText);
            } else {
                errMessage = 'Ajax client error' + (textStatus ? ' (' + textStatus + ')' : '') + (errorThrown ? ': ' + errorThrown : '');
            }

            // Display returned error
            console.log(errMessage);
            cb(new Error(errMessage));
        });
    }

    var pollingServer = false;

    function startPollingServer() {
        function doPollServer() {
            pollServer(function (error, result) {
                if (error) {
                    // Error polling server. Stop polling server, close all currently open
                    //  notification channels, and emit error event from ApiProxy object
                    pollingServer = false;
                    context.ctnApiProxy._closeAllNotifyChannels();
                    context.ctnApiProxy.emitEvent('comm-error', [error]);
                }
                else {
                    if (result.notifyCommands) {
                        result.notifyCommands.forEach(function (command) {
                            processNotifyCommand(command);
                        });
                    }
                }

                if (pollingServer) {
                    setImmediate(doPollServer);
                }
            });
        }

        if (!pollingServer) {
            pollingServer = true;

            doPollServer();
        }
    }

    function stopPollingServer() {
        pollingServer = false;
    }

    function random(length) {
        var validChars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        var numChars = validChars.length;
        var result = '';

        if (context.crypto) {
            // Use cryptographically secure implementation
            var array = new Uint8Array(length);
            context.crypto.getRandomValues(array);
            array = array.map(function (x) {
                return validChars.charCodeAt(x % numChars)
            });
            result = String.fromCharCode.apply(null, array);
        }
        else {
            // Use less secure implementation
            for (idx = 0; idx < length; idx++) {
                result += validChars.charAt(Math.floor(Math.random() * numChars));
            }
        }

        return result;
    }

    function processNotifyCommand(command) {
        var channelId = command.data.channelId;

        // Retrieve notification channel instance
        wsNotifyChannel = context.ctnApiProxy._getWsNotifyChannel(channelId);

        if (wsNotifyChannel) {
            if (command) {
                switch (command.cmd) {
                    case 'notification':
                        // Emit corresponding event from notification channel instance object
                        wsNotifyChannel.emitEvent('notify', [command.data.eventData]);
                        break;

                    case 'notify_channel_opened':
                        var eventData;
                        
                        if (command.data.error) {
                            // Error opening notification channel. Clear notification channel
                            //  instance and prepare to return error
                            context.ctnApiProxy._clearWsNotifyChannel(wsNotifyChannel);
                            eventData = [command.data.error];
                        }

                        // Emit corresponding event from notification channel instance object
                        wsNotifyChannel.emitEvent('open', [eventData]);
                        break;

                    case 'notify_channel_error':
                        // Emit corresponding event from notification channel instance object
                        wsNotifyChannel.emitEvent('error', [command.data.error]);
                        break;

                    case 'notify_channel_closed':
                        // Notification channel has been closed. Clear notification channel instance
                        context.ctnApiProxy._clearWsNotifyChannel(wsNotifyChannel);

                        // Emit corresponding event from notification channel instance object
                        wsNotifyChannel.emitEvent('close', [command.data.code, command.data.reason]);
                        break;

                    default:
                        console.error('Unexpected command from notification process:', command.cmd);
                        break;
                }
            }
        }
    }

    context.ctnApiProxy = new ApiProxy();
})(this);