module.exports = {
  async up(db) {
    // Remove the unique index on the 'slug' field
    const indexes = await db.collection('posts').indexes();
    const slugIndex = indexes.find((idx) => JSON.stringify(idx.key) === JSON.stringify({ slug: 1 }));

    if (slugIndex) {
      await db.collection('posts').dropIndex(slugIndex.name);
      console.log(`✅ Dropped unique index: ${slugIndex.name}`);
    } else {
      console.log(`ℹ️ No unique index found on 'slug'.`);
    }

    // Update the 'slug' field to be null for existing documents where it exists
    const result = await db.collection('posts').updateMany(
      { slug: { $exists: true } }, // Only target documents that have a 'slug'
      { $set: { slug: null } } // Set slug to null
    );
    console.log(`✅ Set 'slug' field to null for ${result.modifiedCount} documents`);
  },

  async down(db) {
    // In the down migration, we would restore the unique index on 'slug' and set it back to non-null (if needed)
    // Recreate the unique index on 'slug'
    await db.collection('posts').createIndex({ slug: 1 }, { unique: true });
    console.log(`✅ Recreated unique index on 'slug'`);

    // Restore the 'slug' field with a placeholder value for documents
    const result = await db.collection('posts').updateMany(
      { slug: { $exists: false } }, // Target documents that don't have a 'slug'
      { $set: { slug: "restored-slug-placeholder" } } // Set a placeholder for those
    );
    console.log(`🔁 Restored 'slug' field for ${result.modifiedCount} documents`);
  },
};
