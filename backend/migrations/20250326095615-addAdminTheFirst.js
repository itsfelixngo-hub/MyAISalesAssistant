const bcrypt = require('bcrypt');

module.exports = {
  async up(db, client) {
    const existingUser = await db.collection('users').findOne({ email: 'admin@example.com' });

    if (!existingUser) {
      const saltRounds = 10;
      const hashedPassword = await bcrypt.hash('Admin@123', saltRounds);

      await db.collection('users').insertOne({
        email: 'admin@example.com',
        password: hashedPassword,
        role: 100,
        createdAt: new Date(),
        updatedAt: new Date()
      });
      await db.collection('users').insertOne({
        email: 'editor1@example.com',
        userName: 'editor1',
        password: hashedPassword,
        role: 10,
        createdAt: new Date(),
        updatedAt: new Date()
      });
      console.log('Default admin user created');
    } else {
      console.log('Default admin user already exists');
    }
  },

  async down(db, client) {
    await db.collection('users').deleteOne({ email: 'admin@example.com' });
    await db.collection('users').deleteOne({ email: 'editor1@example.com' });
    console.log('Default admin user removed');
  }
};
