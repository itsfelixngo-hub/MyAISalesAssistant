module.exports = {
  async up(db) {
    const users = db.collection('users');

    const cursor = users.find({
      $or: [
        { niceName: { $exists: false } },
        { displayName: { $exists: false } },
        { avatar: { $exists: false } },
      ],
      email: { $exists: true },
    });

    let updatedCount = 0;

    while (await cursor.hasNext()) {
      const user = await cursor.next();
      if (!user || !user.email) continue;

      const emailPrefix = user.email.split('@')[0];
      const updateFields = {};

      if (user.niceName === undefined) updateFields.niceName = emailPrefix;
      if (user.displayName === undefined) updateFields.displayName = capitalize(emailPrefix);
      if (user.avatar === undefined) updateFields.avatar = '#';

      if (Object.keys(updateFields).length > 0) {
        await users.updateOne(
          { _id: user._id },
          { $set: updateFields }
        );
        updatedCount++;
      }
    }

    console.log(`✅ UP migration complete. Updated ${updatedCount} users.`);
  },

  async down(db) {
    const users = db.collection('users');

    const result = await users.updateMany(
      {},
      {
        $unset: {
          niceName: "",
          displayName: "",
          avatar: "",
        },
      }
    );

    console.log(`↩️ DOWN migration complete. Unset fields in ${result.modifiedCount} users.`);
  }
};

function capitalize(str) {
  return str.charAt(0).toUpperCase() + str.slice(1);
}
