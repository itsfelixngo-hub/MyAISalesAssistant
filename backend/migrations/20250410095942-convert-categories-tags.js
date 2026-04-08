module.exports = {
  /**
   * @param {import('mongodb').Db} db
   * @param {import('mongodb').MongoClient} client
   */
  async up(db, client) {
    const cursor = db.collection('posts').find({});

    const bulkOps = [];

    while (await cursor.hasNext()) {
      const doc = await cursor.next();
      if (!doc) continue;
      const newCategories = (doc.categories || []).map(Number).filter(n => !isNaN(n));
      const newTags = (doc.tags || []).map(Number).filter(n => !isNaN(n));

      bulkOps.push({
        updateOne: {
          filter: { _id: doc._id },
          update: {
            $set: {
              categories: newCategories,
              tags: newTags,
            }
          }
        }
      });
    }

    if (bulkOps.length > 0) {
      await db.collection('posts').bulkWrite(bulkOps);
    }
  },

  /**
   * @param {import('mongodb').Db} db
   * @param {import('mongodb').MongoClient} client
   */
  async down(db, client) {
    const cursor = db.collection('posts').find({});

    const bulkOps = [];

    while (await cursor.hasNext()) {
      const doc = await cursor.next();
      if (!doc) continue;
      const cat = (doc.categories || []).map(v => v.toString());
      const tag = (doc.tags || []).map(v => v.toString());

      bulkOps.push({
        updateOne: {
          filter: { _id: doc._id },
          update: {
            $set: {
              categories: cat,
              tags: tag,
            }
          }
        }
      });
    }

    if (bulkOps.length > 0) {
      await db.collection('posts').bulkWrite(bulkOps);
    }
  }
};