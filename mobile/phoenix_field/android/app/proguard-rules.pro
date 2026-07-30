# Room + WorkManager (workmanager plugin)
# R8 full mode strips the no-arg constructor of WorkDatabase_Impl, which is
# invoked reflectively at app startup via androidx.startup.InitializationProvider.
-keep class * extends androidx.room.RoomDatabase {
    <init>();
    public ** createInvalidationTracker();
    public void clearAllTables();
}
-keep class androidx.room.RoomDatabase$JournalMode { *; }
-keep class androidx.work.** { *; }
-keep class androidx.work.impl.** { *; }
-keep class * extends androidx.work.ListenableWorker {
    public <init>(android.content.Context, androidx.work.WorkerParameters);
}
-dontwarn androidx.work.**
