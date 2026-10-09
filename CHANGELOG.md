# Changelog

All notable changes to `laravel-azure-service-bus` will be documented in this file.

## Unreleased

- Add Laravel 13 support
- Implement `pendingSize()`, `delayedSize()`, `reservedSize()` and `creationTimeOfOldestPendingJob()` on the queue driver, required by the Laravel 13 queue contract
- Add `AzureServiceBusClient::getScheduledMessageCount()`
- Fix `getMessageCount()` returning the total message count instead of the active message count

## 1.0.0 - 2024-01-01

- Initial release
