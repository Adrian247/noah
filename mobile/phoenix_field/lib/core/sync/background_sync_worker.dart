import 'package:phoenix_field/core/sync/sync_runner.dart';
import 'package:workmanager/workmanager.dart';

const backgroundSyncTaskName = 'phoenixBackgroundSync';
const backgroundSyncUniqueName = 'phoenix-periodic-sync';

@pragma('vm:entry-point')
void callbackDispatcher() {
  Workmanager().executeTask((task, inputData) async {
    await runBackgroundSyncIfAuthenticated();
    return true;
  });
}
